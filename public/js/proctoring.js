let proctoringModel = null;
let proctoringVideoElement;
let proctoringCanvasElement;
let proctoringContext;
let proctoringDetectionActive = false;
let proctoringErrorTriggered = false;
let proctoringInitialized = false;

let proctoringGracePeriodTimer = null;
let proctoringGracePeriodSeconds = 0;
let proctoringGracePeriodReason = "";
const PROCTORING_GRACE_PERIOD_DURATION_SECONDS = 15;

let fullscreenCountdownTimer = null;
let fullscreenSecondsLeft = 0;
const FULLSCREEN_GRACE_DURATION = 30;
let isFullscreenCountdownActive = false;
let fullscreenViolationCount = 0; // New: Track fullscreen violations
const MAX_FULLSCREEN_VIOLATIONS = 3; // New: Max allowed fullscreen violations

let tabSwitchCount = 0;
let tabSwitchTimer = null;
let tabSwitchSecondsLeft = 0;
const TAB_SWITCH_GRACE_DURATION = 10;
const MAX_TAB_SWITCHES = 3;

let lastCameraActivityTimestamp = Date.now();
const CAMERA_INACTIVITY_THRESHOLD_MS = 5000;
let audioContext = null;
let analyser = null;
let microphoneStream = null;
const AUDIO_VOLUME_THRESHOLD = 0.1;
let lastActivityTimestamp = Date.now();
let INACTIVITY_THRESHOLD_SECONDS = 60;
let idleDetectionInterval = null;

let proctoringPhotoInterval = null;
const PHOTO_CAPTURE_INTERVAL_MS = 60000;

let currentQuizId = null;
let currentAttemptId = null;
let currentUserId = null;
let baseUrl = '';

let onProctoringConditionsMetCallback = () => {};
let onProctoringConditionsViolatedCallback = () => {};
let onProctoringCriticalErrorCallback = () => {};
let sendProctoringLogCallback = (eventType, data) => {
    console.log(`Proctoring Log: ${eventType}`, data);
};

window.proctoringShowCustomMessageBox = (title, message, callback = null) => {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay active';
    modal.innerHTML = `
        <div class="modal-content">
            <h3 class="modal-title">${title}</h3>
            <p class="modal-message">${message}</p>
            <button class="modal-button primary-button" id="quizMsgBoxOkBtn">OK</button>
        </div>
    `;
    document.body.appendChild(modal);
    document.getElementById('quizMsgBoxOkBtn').onclick = () => {
        modal.remove();
        if (callback) callback();
    };
};

const DOM = {
    proctoringStatusDisplay: document.getElementById('proctoringStatusText'),
    proctoringFaceCountDisplay: document.getElementById('faceCountDisplay'),
    startAssessmentOverlay: document.getElementById('startAssessmentOverlay'),
    startAssessmentButton: document.getElementById('startAssessmentButton'),
    proctoringErrorModalOverlay: document.getElementById('proctoringErrorModalOverlay'),
    proctoringModalErrorMessage: document.getElementById('proctoringModalErrorMessage'),
    proctoringModalCloseButton: document.getElementById('proctoringModalCloseButton'),
    proctoringFullscreenPromptOverlay: document.getElementById('proctoringFullscreenPromptOverlay'),
    proctoringReEnterFullscreenButton: document.getElementById('proctoringReEnterFullscreenButton'),
    proctoringCancelAssessmentButton: document.getElementById('proctoringCancelAssessmentButton'),
    fullscreenCountdownText: document.getElementById('fullscreenCountdownText'),
    proctoringAutoSubmitModalOverlay: document.getElementById('proctoringAutoSubmitModalOverlay'),
    proctoringAutoSubmitMessage: document.getElementById('proctoringAutoSubmitMessage'),
    proctoringAutoSubmitCloseButton: document.getElementById('proctoringAutoSubmitCloseButton'),
    tabSwitchWarningOverlay: document.getElementById('tabSwitchWarningOverlay'),
    returnToTabButton: document.getElementById('returnToTabButton'),
    quizContent: document.getElementById('quizContent')
};

function initProctoring(callbacks) {
    if (proctoringInitialized) return;

    onProctoringConditionsMetCallback = callbacks.onConditionsMet || (() => {});
    onProctoringConditionsViolatedCallback = callbacks.onConditionsViolated || (() => {});
    onProctoringCriticalErrorCallback = callbacks.onCriticalError || (() => {});
    sendProctoringLogCallback = callbacks.sendProctoringLog || ((eventType, data) => console.log(`Proctoring Log: ${eventType}`, data));
    currentQuizId = callbacks.quizId;
    currentAttemptId = callbacks.attemptId;
    currentUserId = callbacks.userId;
    baseUrl = callbacks.baseUrl;

    proctoringVideoElement = document.getElementById('proctoringVideo');
    proctoringCanvasElement = document.getElementById('proctoringCanvas');

    if (!proctoringVideoElement || !proctoringCanvasElement) {
        triggerProctoringCriticalError("Required proctoring elements (video/canvas) are missing.");
        return;
    }
    proctoringContext = proctoringCanvasElement.getContext('2d');

    DOM.startAssessmentButton?.addEventListener('click', startAssessment);
    DOM.proctoringModalCloseButton?.addEventListener('click', hideProctoringErrorModal);
    DOM.proctoringReEnterFullscreenButton?.addEventListener('click', reEnterFullscreenFromPrompt);
    DOM.proctoringCancelAssessmentButton?.addEventListener('click', cancelAssessmentFromFullscreenPrompt);
    DOM.proctoringAutoSubmitCloseButton?.addEventListener('click', hideProctoringAutoSubmitModal);
    DOM.returnToTabButton?.addEventListener('click', hideTabWarningPopup);

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    document.addEventListener('mousemove', recordActivity);
    document.addEventListener('keypress', recordActivity);
    document.addEventListener('click', recordActivity);
    document.addEventListener('copy', handleCopyAttempt);

    proctoringInitialized = true;
    DOM.startAssessmentOverlay?.classList.remove('hidden');
    DOM.quizContent.style.display = 'block';

    updateProctoringStatus("Click 'Start Assessment' to begin.", 'info');
    sendProctoringLogCallback('assessment_initialized', 'Proctoring system initialized.');
    loadProctoringModel();
}

async function loadProctoringModel() {
    try {
        if (typeof tf === 'undefined' || typeof blazeface === 'undefined') {
            throw new Error("TensorFlow.js or BlazeFace not loaded.");
        }
        proctoringModel = await blazeface.load();
        updateProctoringStatus("Security model loaded. Click 'Start Assessment' to begin.", 'info');
        DOM.startAssessmentButton.disabled = false;
    } catch (error) {
        console.error("Error loading proctoring model:", error);
        triggerProctoringCriticalError("Failed to load security model. Please refresh and try again.");
    }
}

async function startAssessment() {
    if (proctoringErrorTriggered) {
        proctoringShowCustomMessageBox("Assessment Error", "An error occurred. Please refresh to restart.");
        return;
    }

    DOM.startAssessmentOverlay?.classList.add('hidden');
    await startProctoringCamera();
    if (!document.fullscreenElement) {
        try {
            await document.documentElement.requestFullscreen();
        } catch (err) {
            console.error("Fullscreen error:", err);
            triggerProctoringCriticalError("Fullscreen mode access denied or failed.");
        }
    }
    sendProctoringLogCallback('assessment_started', 'User clicked Start Assessment.');
}

async function startProctoringCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        triggerProctoringCriticalError("Webcam not supported by this browser.");
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: true });
        proctoringVideoElement.srcObject = stream;
        proctoringVideoElement.onloadedmetadata = () => {
            proctoringCanvasElement.width = proctoringVideoElement.videoWidth;
            proctoringCanvasElement.height = proctoringVideoElement.videoHeight;
            proctoringVideoElement.play();
            lastCameraActivityTimestamp = Date.now();
            DOM.proctoringFaceCountDisplay.textContent = "Camera ready.";
            startAudioMonitoring(stream);
            startPhotoCapture();
            proctoringDetectionActive = true;
            detectFaces();
            updateProctoringConditions();
        };
        sendProctoringLogCallback('camera_started', 'Webcam stream initiated.');
    } catch (error) {
        console.error("Error accessing webcam:", error);
        let errorMessage = "Camera access denied or failed.";
        if (error.name === "NotAllowedError") {
            errorMessage = "Camera access denied. Please allow camera access.";
        } else if (error.name === "NotFoundError") {
            errorMessage = "No camera found. Please ensure a webcam is connected.";
        }
        updateProctoringStatus(errorMessage, 'error');
        triggerProctoringCriticalError(errorMessage);
    }
}

function startAudioMonitoring(stream) {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 256;
    }

    if (microphoneStream) {
        microphoneStream.disconnect();
        microphoneStream.mediaStream.getTracks().forEach(track => track.stop());
    }

    microphoneStream = audioContext.createMediaStreamSource(stream);
    microphoneStream.connect(analyser);

    const dataArray = new Uint8Array(analyser.frequencyBinCount);
    function checkAudio() {
        if (!proctoringDetectionActive || proctoringErrorTriggered) return;
        analyser.getByteFrequencyData(dataArray);
        const average = dataArray.reduce((sum, val) => sum + val, 0) / dataArray.length;
        const normalizedVolume = average / 255;
        if (normalizedVolume > AUDIO_VOLUME_THRESHOLD) {
            sendProctoringLogCallback('audio_anomaly', { volume: normalizedVolume.toFixed(2) });
        }
        requestAnimationFrame(checkAudio);
    }
    checkAudio();
}

async function detectFaces() {
    if (!proctoringModel || !proctoringVideoElement.videoWidth || !proctoringDetectionActive || proctoringErrorTriggered) {
        if (!proctoringErrorTriggered) requestAnimationFrame(detectFaces);
        return;
    }

    try {
        const predictions = await proctoringModel.estimateFaces(proctoringVideoElement, false);
        proctoringContext.clearRect(0, 0, proctoringCanvasElement.width, proctoringCanvasElement.height);

        if (proctoringVideoElement.videoWidth === 0 || proctoringVideoElement.paused || proctoringVideoElement.ended) {
            startProctoringGracePeriod("Webcam stream unavailable or paused.");
            sendProctoringLogCallback('webcam_tampering', 'Webcam stream not active.');
        } else {
            const currentTime = Date.now();
            if (proctoringVideoElement.currentTime !== proctoringVideoElement._prevTime) {
                lastCameraActivityTimestamp = currentTime;
                proctoringVideoElement._prevTime = proctoringVideoElement.currentTime;
            } else if (currentTime - lastCameraActivityTimestamp > CAMERA_INACTIVITY_THRESHOLD_MS) {
                startProctoringGracePeriod("Webcam activity paused or blocked.");
                sendProctoringLogCallback('webcam_tampering', 'No recent webcam activity.');
            }
        }

        if (predictions.length === 0) {
            DOM.proctoringFaceCountDisplay.textContent = `No faces detected (${proctoringGracePeriodSeconds}s)`;
            startProctoringGracePeriod("No face detected in camera feed.");
        } else if (predictions.length === 1) {
            DOM.proctoringFaceCountDisplay.textContent = "1 face detected";
            clearProctoringGracePeriod();
            proctoringContext.strokeStyle = '#00FF00';
            proctoringContext.lineWidth = 4;
            proctoringContext.fillStyle = 'rgba(0, 255, 0, 0.1)';
            predictions.forEach(prediction => {
                const start = prediction.topLeft;
                const end = prediction.bottomRight;
                const size = [end[0] - start[0], end[1] - start[1]];
                proctoringContext.fillRect(start[0], start[1], size[0], size[1]);
                proctoringContext.strokeRect(start[0], start[1], size[0], size[1]);
                if (prediction.landmarks) {
                    proctoringContext.fillStyle = '#FF0000';
                    prediction.landmarks.forEach(landmark => {
                        proctoringContext.fillRect(landmark[0], landmark[1], 4, 4);
                    });
                    const nose = prediction.landmarks[2];
                    const faceCenterX = start[0] + size[0] / 2;
                    const headTurnThreshold = 50;
                    if (Math.abs(nose[0] - faceCenterX) > headTurnThreshold) {
                        sendProctoringLogCallback('head_pose_anomaly', 'User likely looking away.');
                    }
                }
            });
        } else {
            DOM.proctoringFaceCountDisplay.textContent = `${predictions.length} faces detected (${proctoringGracePeriodSeconds}s)`;
            startProctoringGracePeriod("More than one face detected.");
        }

        if (window.screen.width > window.innerWidth || window.screen.height > window.innerHeight) {
            sendProctoringLogCallback('multiple_monitor_detected', 'Potential multiple monitor setup.');
        }

        if (proctoringDetectionActive && !proctoringErrorTriggered) {
            requestAnimationFrame(detectFaces);
        }
    } catch (error) {
        console.error("Face detection error:", error);
        triggerProctoringCriticalError("Face detection encountered an error.");
    }
}

function startProctoringGracePeriod(reason) {
    if (proctoringErrorTriggered || (proctoringGracePeriodTimer && proctoringGracePeriodReason === reason)) return;

    clearProctoringGracePeriod();
    proctoringGracePeriodReason = reason;
    proctoringGracePeriodSeconds = PROCTORING_GRACE_PERIOD_DURATION_SECONDS;
    updateProctoringStatus(`Warning: ${reason} Correct in ${proctoringGracePeriodSeconds}s.`, 'warning');
    onProctoringConditionsViolatedCallback();

    proctoringGracePeriodTimer = setInterval(() => {
        proctoringGracePeriodSeconds--;
        if (proctoringGracePeriodSeconds <= 0) {
            clearProctoringGracePeriod();
            triggerProctoringCriticalError(`${reason} persisted for ${PROCTORING_GRACE_PERIOD_DURATION_SECONDS}s.`);
        } else {
            updateProctoringStatus(`Warning: ${reason} Correct in ${proctoringGracePeriodSeconds}s.`, 'warning');
            if (reason.includes("face")) {
                DOM.proctoringFaceCountDisplay.textContent = DOM.proctoringFaceCountDisplay.textContent.replace(/\(\d+s\)/, `(${proctoringGracePeriodSeconds}s)`);
            }
        }
    }, 1000);
    sendProctoringLogCallback('grace_period_started', { reason, duration: PROCTORING_GRACE_PERIOD_DURATION_SECONDS });
}

function clearProctoringGracePeriod() {
    if (proctoringGracePeriodTimer) {
        clearInterval(proctoringGracePeriodTimer);
        proctoringGracePeriodTimer = null;
        proctoringGracePeriodSeconds = 0;
        proctoringGracePeriodReason = "";
        updateProctoringConditions();
        sendProctoringLogCallback('grace_period_cleared', 'Violation corrected.');
    }
}

function showTabWarningPopup() {
    if (DOM.tabSwitchWarningOverlay) {
        const messageElement = DOM.tabSwitchWarningOverlay.querySelector('.modal-message');
        if (messageElement) {
            messageElement.innerHTML = `You have left the assessment tab ${tabSwitchCount} time${tabSwitchCount === 1 ? '' : 's'}. Please click "Return to Assessment" to continue. You have ${MAX_TAB_SWITCHES - tabSwitchCount} warning${MAX_TAB_SWITCHES - tabSwitchCount === 1 ? '' : 's'} remaining before assessment termination.`;
        }
        DOM.tabSwitchWarningOverlay.classList.remove('hidden');
        DOM.tabSwitchWarningOverlay.classList.add('active');
    }
}

function hideTabWarningPopup() {
    if (DOM.tabSwitchWarningOverlay && !document.hidden) {
        DOM.tabSwitchWarningOverlay.classList.add('hidden');
        DOM.tabSwitchWarningOverlay.classList.remove('active');
        clearTabSwitchTimer();
        if (proctoringGracePeriodReason.includes("You left the assessment tab")) {
            clearProctoringGracePeriod();
        }
        updateProctoringConditions();
    }
}

function startTabSwitchTimer() {
    if (tabSwitchTimer) clearInterval(tabSwitchTimer);
    tabSwitchSecondsLeft = TAB_SWITCH_GRACE_DURATION;
    showTabWarningPopup();

    tabSwitchTimer = setInterval(() => {
        tabSwitchSecondsLeft--;
        const messageElement = DOM.tabSwitchWarningOverlay.querySelector('.modal-message');
        if (messageElement) {
            messageElement.innerHTML = `You have left the assessment tab ${tabSwitchCount} time${tabSwitchCount === 1 ? '' : 's'}. Please click "Return to Assessment" to continue. You have ${MAX_TAB_SWITCHES - tabSwitchCount} warning${MAX_TAB_SWITCHES - tabSwitchCount === 1 ? '' : 's'} remaining before assessment termination. (Return in ${tabSwitchSecondsLeft}s)`;
        }

        if (tabSwitchSecondsLeft <= 0) {
            clearInterval(tabSwitchTimer);
            tabSwitchTimer = null;
            if (document.hidden) {
                sendProctoringLogCallback('tab_switch_grace_expired', 'User remained outside tab.');
            }
        } else {
            updateProctoringStatus(`Warning: You left the assessment tab ${tabSwitchCount} time${tabSwitchCount === 1 ? '' : 's'} (${tabSwitchCount}/${MAX_TAB_SWITCHES}). Return in ${tabSwitchSecondsLeft}s.`, 'warning');
        }
    }, 1000);
    sendProctoringLogCallback('tab_switch_timer_started', { count: tabSwitchCount, duration: TAB_SWITCH_GRACE_DURATION });
}

function clearTabSwitchTimer() {
    if (tabSwitchTimer) {
        clearInterval(tabSwitchTimer);
        tabSwitchTimer = null;
        tabSwitchSecondsLeft = 0;
        sendProctoringLogCallback('tab_switch_timer_cleared', 'Returned to assessment tab.');
    }
}

function startFullscreenCountdown() {
    if (isFullscreenCountdownActive) return;
    isFullscreenCountdownActive = true;
    fullscreenSecondsLeft = FULLSCREEN_GRACE_DURATION;
    fullscreenViolationCount++; // Increment violation count
    sendProctoringLogCallback('fullscreen_violation', { count: fullscreenViolationCount, max: MAX_FULLSCREEN_VIOLATIONS });

    DOM.fullscreenCountdownText.textContent = `Return to fullscreen in ${fullscreenSecondsLeft}s. You have ${MAX_FULLSCREEN_VIOLATIONS - fullscreenViolationCount} warning${MAX_FULLSCREEN_VIOLATIONS - fullscreenViolationCount === 1 ? '' : 's'} remaining.`;
    DOM.proctoringFullscreenPromptOverlay.classList.remove('hidden');
    DOM.proctoringFullscreenPromptOverlay.classList.add('active');

    fullscreenCountdownTimer = setInterval(() => {
        fullscreenSecondsLeft--;
        if (fullscreenSecondsLeft <= 0) {
            clearInterval(fullscreenCountdownTimer);
            fullscreenCountdownTimer = null;
            isFullscreenCountdownActive = false;
            if (!document.fullscreenElement) {
                const assessmentForm = document.querySelector('form');
                if (assessmentForm) {
                    showProctoringAutoSubmitModal(`Assessment submitted due to excessive fullscreen violations (${fullscreenViolationCount}/${MAX_FULLSCREEN_VIOLATIONS}).`);
                    assessmentForm.submit();
                    sendProctoringLogCallback('form_submitted', 'Submitted due to excessive fullscreen violations.');
                } else {
                    triggerProctoringCriticalError("Assessment form not found after excessive fullscreen violations.");
                }
            }
        } else {
            DOM.fullscreenCountdownText.textContent = `Return to fullscreen in ${fullscreenSecondsLeft}s. You have ${MAX_FULLSCREEN_VIOLATIONS - fullscreenViolationCount} warning${MAX_FULLSCREEN_VIOLATIONS - fullscreenViolationCount === 1 ? '' : 's'} remaining.`;
            updateProctoringStatus(`Warning: You exited fullscreen ${fullscreenViolationCount} time${fullscreenViolationCount === 1 ? '' : 's'} (${fullscreenViolationCount}/${MAX_FULLSCREEN_VIOLATIONS}). Return in ${fullscreenSecondsLeft}s.`, 'warning');
        }
    }, 1000);
    sendProctoringLogCallback('fullscreen_countdown_started', { duration: FULLSCREEN_GRACE_DURATION, count: fullscreenViolationCount });
}

function clearFullscreenCountdown() {
    if (fullscreenCountdownTimer) {
        clearInterval(fullscreenCountdownTimer);
        fullscreenCountdownTimer = null;
        fullscreenSecondsLeft = 0;
        isFullscreenCountdownActive = false;
        DOM.fullscreenCountdownText.textContent = `Remain in fullscreen mode.`;
        DOM.proctoringFullscreenPromptOverlay.classList.add('hidden');
        DOM.proctoringFullscreenPromptOverlay.classList.remove('active');
        if (proctoringGracePeriodReason.includes("fullscreen")) {
            clearProctoringGracePeriod();
        }
        sendProctoringLogCallback('fullscreen_countdown_cleared', 'Returned to fullscreen.');
    }
}

function showProctoringAutoSubmitModal(message) {
    DOM.proctoringAutoSubmitMessage.textContent = message;
    DOM.proctoringAutoSubmitModalOverlay.classList.remove('hidden');
    DOM.proctoringAutoSubmitModalOverlay.classList.add('active');
}

function hideProctoringAutoSubmitModal() {
    DOM.proctoringAutoSubmitModalOverlay.classList.add('hidden');
    DOM.proctoringAutoSubmitModalOverlay.classList.remove('active');
}

function updateProctoringStatus(text, type = 'info') {
    const statusBox = DOM.proctoringStatusDisplay.closest('.status-box');
    if (statusBox) {
        statusBox.className = `status-box ${type}`;
    }
    DOM.proctoringStatusDisplay.textContent = text;
    sendProctoringLogCallback('status_update', { message: text, type });
}

function updateProctoringConditions() {
    if (proctoringErrorTriggered || !proctoringInitialized) return;

    const isFullscreen = !!document.fullscreenElement;
    const isTabVisible = !document.hidden;
    const isModelLoaded = proctoringModel !== null;
    const isCameraReady = proctoringVideoElement && proctoringVideoElement.srcObject && proctoringVideoElement.readyState >= 2;

    if (isFullscreen && isTabVisible && isModelLoaded && isCameraReady && !proctoringGracePeriodTimer) {
        clearFullscreenCountdown();
        clearTabSwitchTimer();
        DOM.startAssessmentOverlay?.classList.add('hidden');

        if (!proctoringDetectionActive) {
            proctoringDetectionActive = true;
            detectFaces();
            startIdleDetection();
        }
        updateProctoringStatus("Proctoring active.", 'success');
        onProctoringConditionsMetCallback();
    } else {
        if (proctoringDetectionActive) {
            proctoringDetectionActive = false;
            proctoringContext.clearRect(0, 0, proctoringCanvasElement.width, proctoringCanvasElement.height);
            stopIdleDetection();
        }
        onProctoringConditionsViolatedCallback();

        if (!isFullscreen) {
            updateProctoringStatus(`Warning: You exited fullscreen ${fullscreenViolationCount} time${fullscreenViolationCount === 1 ? '' : 's'} (${fullscreenViolationCount}/${MAX_FULLSCREEN_VIOLATIONS}).`, 'warning');
            if (!isFullscreenCountdownActive && fullscreenViolationCount <= MAX_FULLSCREEN_VIOLATIONS) {
                startFullscreenCountdown();
            }
        } else if (!isTabVisible) {
            updateProctoringStatus(`Warning: You left the assessment tab ${tabSwitchCount} time${tabSwitchCount === 1 ? '' : 's'} (${tabSwitchCount}/${MAX_TAB_SWITCHES}).`, 'warning');
            if (!tabSwitchTimer && tabSwitchCount <= MAX_TAB_SWITCHES) {
                startTabSwitchTimer();
            }
        } else if (!isModelLoaded || !isCameraReady) {
            updateProctoringStatus("Camera or security model not ready.", 'warning');
            DOM.startAssessmentOverlay?.classList.remove('hidden');
            DOM.startAssessmentButton.disabled = true;
        } else {
            updateProctoringStatus("Proctoring conditions not met.", 'warning');
        }
    }
}

function triggerProctoringCriticalError(message) {
    if (proctoringErrorTriggered) return;
    proctoringErrorTriggered = true;
    proctoringDetectionActive = false;

    if (proctoringVideoElement && proctoringVideoElement.srcObject) {
        proctoringVideoElement.srcObject.getTracks().forEach(track => track.stop());
    }
    if (audioContext) {
        audioContext.close();
        audioContext = null;
        analyser = null;
        microphoneStream = null;
    }
    stopPhotoCapture();
    clearProctoringGracePeriod();
    clearFullscreenCountdown();
    clearTabSwitchTimer();
    stopIdleDetection();

    updateProctoringStatus(`Critical Error: ${message}`, 'error');
    DOM.proctoringModalErrorMessage.textContent = `Assessment terminated: ${message}`;
    DOM.proctoringErrorModalOverlay.classList.remove('hidden');
    DOM.proctoringErrorModalOverlay.classList.add('active');
    onProctoringCriticalErrorCallback(message);
    sendProctoringLogCallback('critical_error', message);
}

function hideProctoringErrorModal() {
    DOM.proctoringErrorModalOverlay.classList.add('hidden');
    DOM.proctoringErrorModalOverlay.classList.remove('active');
}

async function reEnterFullscreenFromPrompt() {
    DOM.proctoringFullscreenPromptOverlay.classList.add('hidden');
    DOM.proctoringFullscreenPromptOverlay.classList.remove('active');
    try {
        await document.documentElement.requestFullscreen();
        sendProctoringLogCallback('fullscreen_reentered', 'User re-entered fullscreen.');
    } catch (err) {
        console.error("Re-enter fullscreen error:", err);
        triggerProctoringCriticalError(`Failed to re-enter fullscreen: ${err.message}`);
    }
}

function cancelAssessmentFromFullscreenPrompt() {
    DOM.proctoringFullscreenPromptOverlay.classList.add('hidden');
    DOM.proctoringFullscreenPromptOverlay.classList.remove('active');
    const assessmentForm = document.querySelector('form');
    if (assessmentForm) {
        showProctoringAutoSubmitModal("Assessment submitted due to user cancellation from fullscreen prompt.");
        assessmentForm.submit();
        sendProctoringLogCallback('form_submitted', 'Submitted due to user cancellation from fullscreen prompt.');
    } else {
        triggerProctoringCriticalError("Assessment form not found. User canceled the assessment.");
    }
}

function handleFullscreenChange() {
    const headers = document.querySelectorAll('header, .header');
    const footers = document.querySelectorAll('footer, .footer');
    headers.forEach(el => el.style.display = document.fullscreenElement ? 'none' : '');
    footers.forEach(el => el.style.display = document.fullscreenElement ? '' : '');
    updateProctoringConditions();
}

function handleVisibilityChange() {
    if (!document.hidden) {
        updateProctoringConditions();
    } else {
        tabSwitchCount++;
        sendProctoringLogCallback('tab_switch', { count: tabSwitchCount, max: MAX_TAB_SWITCHES });
        showTabWarningPopup();

        if (tabSwitchCount > MAX_TAB_SWITCHES) {
            const assessmentForm = document.querySelector('form');
            if (assessmentForm) {
                showProctoringAutoSubmitModal(`Assessment submitted due to excessive tab switches (${tabSwitchCount}/${MAX_TAB_SWITCHES} violations).`);
                assessmentForm.submit();
                sendProctoringLogCallback('form_submitted', 'Submitted due to excessive tab switches.');
            } else {
                triggerProctoringCriticalError("Assessment form not found after excessive tab switches.");
            }
        } else {
            startTabSwitchTimer();
        }
        updateProctoringConditions();
    }
}

function recordActivity() {
    lastActivityTimestamp = Date.now();
    if (proctoringGracePeriodReason.includes("No activity")) {
        clearProctoringGracePeriod();
    }
}

function startIdleDetection() {
    if (idleDetectionInterval) clearInterval(idleDetectionInterval);
    idleDetectionInterval = setInterval(() => {
        if (Date.now() - lastActivityTimestamp > INACTIVITY_THRESHOLD_SECONDS * 1000) {
            startProctoringGracePeriod(`No activity detected for ${INACTIVITY_THRESHOLD_SECONDS}s.`);
            sendProctoringLogCallback('idle_detected', { duration: INACTIVITY_THRESHOLD_SECONDS });
        }
    }, 5000);
}

function stopIdleDetection() {
    if (idleDetectionInterval) {
        clearInterval(idleDetectionInterval);
        idleDetectionInterval = null;
    }
}

function handleCopyAttempt(event) {
    sendProctoringLogCallback('copy_attempt', 'User attempted to copy content.');
    startProctoringGracePeriod("Copying content is not allowed.");
}

function startPhotoCapture() {
    if (proctoringPhotoInterval) clearInterval(proctoringPhotoInterval);
    capturePhoto();
    proctoringPhotoInterval = setInterval(capturePhoto, PHOTO_CAPTURE_INTERVAL_MS);
    sendProctoringLogCallback('photo_capture_started', `Photo capture every ${PHOTO_CAPTURE_INTERVAL_MS / 1000}s.`);
}

function stopPhotoCapture() {
    if (proctoringPhotoInterval) {
        clearInterval(proctoringPhotoInterval);
        proctoringPhotoInterval = null;
        sendProctoringLogCallback('photo_capture_stopped', 'Photo capture stopped.');
    }
}

function capturePhoto() {
    if (!proctoringVideoElement || !proctoringCanvasElement || proctoringVideoElement.paused || proctoringVideoElement.ended || !proctoringVideoElement.srcObject) {
        console.warn("Cannot capture photo: Video stream not active.");
        return;
    }

    proctoringCanvasElement.width = proctoringVideoElement.videoWidth;
    proctoringCanvasElement.height = proctoringVideoElement.videoHeight;
    proctoringContext.drawImage(proctoringVideoElement, 0, 0, proctoringCanvasElement.width, proctoringCanvasElement.height);
    const imageData = proctoringCanvasElement.toDataURL('image/png');
    sendProctoringPhoto(imageData);
}

async function sendProctoringPhoto(imageData) {
    if (!currentQuizId || !currentAttemptId || !currentUserId || !baseUrl) {
        console.error("Photo upload failed: Missing IDs or base URL.");
        sendProctoringLogCallback('photo_upload_failed', 'Missing IDs or base URL.');
        return;
    }

    try {
        const response = await fetch(`${baseUrl}student/capture_photo.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                quiz_id: currentQuizId,
                attempt_id: currentAttemptId,
                user_id: currentUserId,
                image_data: imageData
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Photo upload failed:', response.status, errorText);
            sendProctoringLogCallback('photo_upload_failed', { status: response.status, error: errorText });
        } else {
            const result = await response.json();
            if (result.success) {
                sendProctoringLogCallback('photo_uploaded', `Photo uploaded: ${result.image_path}`);
            } else {
                console.error('Server error:', result.message);
                sendProctoringLogCallback('photo_upload_failed', { message: result.message });
            }
        }
    } catch (error) {
        console.error('Network error during photo upload:', error);
        sendProctoringLogCallback('photo_upload_failed', { error: error.message });
    }
}

window.initProctoring = initProctoring;