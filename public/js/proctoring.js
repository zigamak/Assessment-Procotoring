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

// New variable for tracking tab switches
let tabSwitchCount = 0;
const MAX_TAB_SWITCHES = 3;

// New variables for additional proctoring features
let lastCameraActivityTimestamp = Date.now();
const CAMERA_INACTIVITY_THRESHOLD_MS = 5000; // 5 seconds
let audioContext = null;
let analyser = null;
let microphoneStream = null;
const AUDIO_VOLUME_THRESHOLD = 0.1; // Adjust as needed, based on normalized volume
let lastActivityTimestamp = Date.now();
const INACTIVITY_THRESHOLD_SECONDS = 60; // 60 seconds of no mouse/keyboard activity

// New variables for photo capture
let proctoringPhotoInterval = null;
const PHOTO_CAPTURE_INTERVAL_MS = 60000; // Capture photo every 60 seconds (60 * 1000 ms)

// Variables to hold quiz/user/attempt IDs and base URL passed from PHP
let currentQuizId = null;
let currentAttemptId = null;
let currentUserId = null;
let baseUrl = ''; // Will be set by initProctoring

let onProctoringConditionsMetCallback = () => {};
let onProctoringConditionsViolatedCallback = () => {};
let onProctoringCriticalErrorCallback = () => {};
let sendProctoringLogCallback = (eventType, data) => { console.log(`Proctoring Log (Unsent): ${eventType}`, data); };

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

const proctoringStatusDisplay = document.getElementById('proctoringStatusText');
const proctoringFaceCountDisplay = document.getElementById('faceCountDisplay');
const startAssessmentOverlay = document.getElementById('startAssessmentOverlay');
const startAssessmentButton = document.getElementById('startAssessmentButton');

const proctoringErrorModalOverlay = document.getElementById('proctoringErrorModalOverlay');
const proctoringModalErrorMessage = document.getElementById('proctoringModalErrorMessage');
const proctoringModalCloseButton = document.getElementById('proctoringModalCloseButton');

const proctoringFullscreenPromptOverlay = document.getElementById('proctoringFullscreenPromptOverlay');
const proctoringReEnterFullscreenButton = document.getElementById('proctoringReEnterFullscreenButton');
const proctoringCancelAssessmentButton = document.getElementById('proctoringCancelAssessmentButton');
const fullscreenCountdownText = document.getElementById('fullscreenCountdownText');

const proctoringAutoSubmitModalOverlay = document.getElementById('proctoringAutoSubmitModalOverlay');
const proctoringAutoSubmitMessage = document.getElementById('proctoringAutoSubmitMessage');
const proctoringAutoSubmitCloseButton = document.getElementById('proctoringAutoSubmitCloseButton');

function initProctoring(callbacks) {
    // Assign external callbacks
    if (callbacks.onConditionsMet) onProctoringConditionsMetCallback = callbacks.onConditionsMet;
    if (callbacks.onConditionsViolated) onProctoringConditionsViolatedCallback = callbacks.onConditionsViolated;
    if (callbacks.onCriticalError) onProctoringCriticalErrorCallback = callbacks.onCriticalError;
    if (callbacks.sendProctoringLog) sendProctoringLogCallback = callbacks.sendProctoringLog;

    // Assign IDs and base URL passed from PHP
    currentQuizId = callbacks.quizId;
    currentAttemptId = callbacks.attemptId;
    currentUserId = callbacks.userId;
    baseUrl = callbacks.baseUrl; // Ensure BASE_URL from PHP is passed here

    proctoringVideoElement = document.getElementById('proctoringVideo');
    proctoringCanvasElement = document.getElementById('proctoringCanvas');

    // Essential check: If elements are not found, trigger critical error.
    if (!proctoringVideoElement || !proctoringCanvasElement) {
        console.error("Proctoring: Video or Canvas element not found. Cannot initialize.");
        triggerProctoringCriticalError("Required proctoring elements are missing (video/canvas). Please ensure they are in the HTML.");
        return;
    }
    proctoringContext = proctoringCanvasElement.getContext('2d');


    startAssessmentButton?.addEventListener('click', requestFullscreenAndStartProctoring);
    proctoringModalCloseButton?.addEventListener('click', hideProctoringErrorModal);
    proctoringReEnterFullscreenButton?.addEventListener('click', reEnterFullscreenFromPrompt);
    proctoringCancelAssessmentButton?.addEventListener('click', cancelAssessmentFromFullscreenPrompt);
    proctoringAutoSubmitCloseButton?.addEventListener('click', hideProctoringAutoSubmitModal);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // New event listeners for activity tracking
    document.addEventListener('mousemove', recordActivity);
    document.addEventListener('keypress', recordActivity);
    document.addEventListener('click', recordActivity);

    // New event listener for copy/paste detection (conceptual)
    document.addEventListener('copy', handleCopyAttempt);

    if (startAssessmentOverlay) {
        startAssessmentOverlay.classList.remove('hidden');
    }

    updateProctoringStatus("Click 'Start Assessment' to begin.", 'info');
    sendProctoringLogCallback('assessment_initialized', 'Proctoring system initialized.');
    loadProctoringModel();
}

function updateProctoringStatus(text, type = 'info') {
    const statusBox = proctoringStatusDisplay.closest('.status-box');
    if (statusBox) {
        statusBox.classList.remove('info', 'success', 'warning', 'error');
        statusBox.classList.add(type);
    }
    proctoringStatusDisplay.textContent = text;
    sendProctoringLogCallback('status_update', { message: text, type: type });
}

async function loadProctoringModel() {
    try {
        // Ensure TensorFlow.js and BlazeFace are loaded
        if (typeof tf === 'undefined' || typeof blazeface === 'undefined') {
            throw new Error("TensorFlow.js or BlazeFace library not loaded. Check script includes.");
        }
        proctoringModel = await blazeface.load();
        updateProctoringStatus("Security model loaded. Click 'Start Assessment' to begin proctoring.", 'info');
        if (startAssessmentButton) {
            startAssessmentButton.disabled = false;
        }
    } catch (error) {
        console.error("Error loading proctoring model:", error);
        triggerProctoringCriticalError("Failed to load security model. Please refresh and try again.");
    }
}

async function startProctoringCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.warn('Webcam not supported by this browser.');
        updateProctoringStatus('Webcam not supported by your browser.', 'error');
        triggerProctoringCriticalError("Webcam not supported or access denied.");
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: true });
        proctoringVideoElement.srcObject = stream;
        proctoringVideoElement.onloadedmetadata = () => {
            proctoringCanvasElement.width = proctoringVideoElement.videoWidth;
            proctoringCanvasElement.height = proctoringVideoElement.videoHeight;
            proctoringFaceCountDisplay.textContent = "Camera ready.";
            proctoringVideoElement.play();
            lastCameraActivityTimestamp = Date.now();
            startAudioMonitoring(stream);
            startPhotoCapture(); // START PHOTO CAPTURE HERE
            updateProctoringConditions();
        };
        sendProctoringLogCallback('camera_started', 'Webcam stream initiated successfully.');
    } catch (error) {
        console.error("Error accessing webcam:", error);
        let errorMessage = "Camera access denied or failed.";
        if (error.name === "NotAllowedError") {
            errorMessage = "Camera access was denied. Please allow camera access in your browser settings.";
        } else if (error.name === "NotFoundError") {
            errorMessage = "No camera found. Please ensure a webcam is connected.";
        }
        updateProctoringStatus(errorMessage, 'error');
        triggerProctoringCriticalError(errorMessage + " Please allow camera access and refresh the page.");
    }
}

function startAudioMonitoring(stream) {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (!analyser) {
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
        let sum = 0;
        for (let i = 0; i < dataArray.length; i++) {
            sum += dataArray[i];
        }
        const average = sum / dataArray.length;
        const normalizedVolume = average / 255;

        if (normalizedVolume > AUDIO_VOLUME_THRESHOLD) {
            sendProctoringLogCallback('audio_anomaly', { volume: normalizedVolume.toFixed(2), timestamp: Date.now() });
        }
        requestAnimationFrame(checkAudio);
    }
    checkAudio();
}

async function detectFaces() {
    if (!proctoringModel || !proctoringVideoElement.videoWidth || !proctoringDetectionActive || proctoringErrorTriggered || proctoringFullscreenPromptOverlay.classList.contains('active')) {
        if (!proctoringErrorTriggered && !proctoringFullscreenPromptOverlay.classList.contains('active')) {
            requestAnimationFrame(detectFaces);
        }
        return;
    }

    try {
        const predictions = await proctoringModel.estimateFaces(proctoringVideoElement, false);
        proctoringContext.clearRect(0, 0, proctoringCanvasElement.width, proctoringCanvasElement.height);

        if (proctoringVideoElement.videoWidth === 0 || proctoringVideoElement.videoHeight === 0 || proctoringVideoElement.paused || proctoringVideoElement.ended) {
            startProctoringGracePeriod("Webcam stream is unavailable or paused.");
            sendProctoringLogCallback('webcam_tampering', 'Webcam stream is not active.');
        } else {
            const currentTime = Date.now();
            if (proctoringVideoElement.currentTime !== proctoringVideoElement._prevTime) {
                lastCameraActivityTimestamp = currentTime;
                proctoringVideoElement._prevTime = proctoringVideoElement.currentTime;
            } else if (currentTime - lastCameraActivityTimestamp > CAMERA_INACTIVITY_THRESHOLD_MS) {
                startProctoringGracePeriod("Webcam activity paused or blocked.");
                sendProctoringLogCallback('webcam_tampering', 'No recent webcam activity detected.');
            }
        }

        if (predictions.length === 0) {
            proctoringFaceCountDisplay.textContent = `No faces detected (${proctoringGracePeriodSeconds}s)`;
            startProctoringGracePeriod("No face detected in camera feed.");
        } else if (predictions.length === 1) {
            proctoringFaceCountDisplay.textContent = "1 face detected";
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
                        sendProctoringLogCallback('head_pose_anomaly', 'User is likely looking away from the screen.');
                    }
                }
            });
        } else {
            proctoringFaceCountDisplay.textContent = `${predictions.length} faces detected (${proctoringGracePeriodSeconds}s)`;
            startProctoringGracePeriod("More than one face detected. Only one person is allowed.");
        }

        if (window.screen.width > window.innerWidth || window.screen.height > window.innerHeight) {
            sendProctoringLogCallback('multiple_monitor_detected', 'Potential multiple monitor setup detected.');
        }

    } catch (error) {
        console.error("Proctoring detection process error:", error);
        if (proctoringDetectionActive && !proctoringErrorTriggered) {
            triggerProctoringCriticalError("Proctoring detection encountered an internal error.");
        }
    }

    if (proctoringDetectionActive && !proctoringErrorTriggered && document.fullscreenElement && !document.hidden && !proctoringFullscreenPromptOverlay.classList.contains('active')) {
        requestAnimationFrame(detectFaces);
    }
}

function startProctoringGracePeriod(reason) {
    if (proctoringErrorTriggered || proctoringFullscreenPromptOverlay.classList.contains('active')) return;

    // IMPORTANT CHANGE: Only prevent starting if it's the EXACT same reason AND already active.
    // This ensures tab switch warnings can repeatedly trigger.
    if (proctoringGracePeriodTimer && proctoringGracePeriodReason === reason) {
        return;
    }

    clearProctoringGracePeriod(); // Clear any existing grace period to start fresh

    proctoringGracePeriodReason = reason;
    proctoringGracePeriodSeconds = PROCTORING_GRACE_PERIOD_DURATION_SECONDS;
    updateProctoringStatus(`Warning: ${reason} Please correct in ${proctoringGracePeriodSeconds} seconds...`, 'warning');
    onProctoringConditionsViolatedCallback();

    proctoringGracePeriodTimer = setInterval(() => {
        proctoringGracePeriodSeconds--;
        if (proctoringGracePeriodSeconds <= 0) {
            clearInterval(proctoringGracePeriodTimer);
            proctoringGracePeriodTimer = null;
            triggerProctoringCriticalError(`${reason} persisted for ${PROCTORING_GRACE_PERIOD_DURATION_SECONDS} seconds.`);
        } else {
            updateProctoringStatus(`Warning: ${reason} Please correct in ${proctoringGracePeriodSeconds} seconds...`, 'warning');
            if (reason.includes("No face") || reason.includes("More than one face")) {
                proctoringFaceCountDisplay.textContent = proctoringFaceCountDisplay.textContent.replace(/\(\d+s\)/, `(${proctoringGracePeriodSeconds}s)`);
            }
        }
    }, 1000);

    sendProctoringLogCallback('grace_period_started', { reason: reason, duration: PROCTORING_GRACE_PERIOD_DURATION_SECONDS });
}

function clearProctoringGracePeriod() {
    if (proctoringGracePeriodTimer) {
        clearInterval(proctoringGracePeriodTimer);
        proctoringGracePeriodTimer = null;
        proctoringGracePeriodSeconds = 0;
        proctoringGracePeriodReason = ""; // Clear the reason when the grace period ends
        updateProctoringConditions(); // Re-evaluate conditions immediately after clearing
        sendProctoringLogCallback('grace_period_cleared', 'Violation corrected.');
    }
}

function startFullscreenCountdown() {
    if (isFullscreenCountdownActive) return;

    isFullscreenCountdownActive = true;
    fullscreenSecondsLeft = FULLSCREEN_GRACE_DURATION;
    fullscreenCountdownText.textContent = `To continue the assessment, you must remain in fullscreen mode. Returning in ${fullscreenSecondsLeft} seconds...`;

    if (fullscreenCountdownTimer) {
        clearInterval(fullscreenCountdownTimer);
    }

    fullscreenCountdownTimer = setInterval(() => {
        fullscreenSecondsLeft--;
        if (fullscreenSecondsLeft <= 0) {
            clearInterval(fullscreenCountdownTimer);
            fullscreenCountdownTimer = null;
            isFullscreenCountdownActive = false;
            triggerProctoringCriticalError(`User did not return to fullscreen mode within ${FULLSCREEN_GRACE_DURATION} seconds.`);
        } else {
            fullscreenCountdownText.textContent = `To continue the assessment, you must remain in fullscreen mode. Returning in ${fullscreenSecondsLeft} seconds...`;
        }
    }, 1000);
    sendProctoringLogCallback('fullscreen_countdown_started', { duration: FULLSCREEN_GRACE_DURATION });
}

function clearFullscreenCountdown() {
    if (fullscreenCountdownTimer) {
        clearInterval(fullscreenCountdownTimer);
        fullscreenCountdownTimer = null;
        fullscreenSecondsLeft = 0;
        isFullscreenCountdownActive = false;
        fullscreenCountdownText.textContent = `To continue the assessment, you must remain in fullscreen mode.`;
        sendProctoringLogCallback('fullscreen_countdown_cleared', 'Returned to fullscreen.');
    }
}

function showProctoringAutoSubmitModal(message) {
    proctoringAutoSubmitMessage.textContent = message;
    proctoringAutoSubmitModalOverlay.classList.add('active');
}

function hideProctoringAutoSubmitModal() {
    proctoringAutoSubmitModalOverlay.classList.remove('active');
}

function updateProctoringConditions() {
    if (proctoringErrorTriggered || !proctoringInitialized) return;

    const isFullscreen = document.fullscreenElement;
    const isTabVisible = !document.hidden;
    const isModelLoaded = proctoringModel !== null;
    const isCameraReady = proctoringVideoElement && proctoringVideoElement.srcObject && proctoringVideoElement.readyState >= 2;

    if (isFullscreen && isTabVisible && isModelLoaded && isCameraReady && !proctoringGracePeriodTimer) {
        clearFullscreenCountdown();
        if (!proctoringDetectionActive) {
            proctoringDetectionActive = true;
            updateProctoringStatus("All conditions met. Proctoring active.", 'success');
            onProctoringConditionsMetCallback();
            detectFaces();
            sendProctoringLogCallback('proctoring_started_or_resumed', 'All proctoring conditions are met.');
            startIdleDetection();
        } else {
            updateProctoringStatus("All conditions met. Proctoring active.", 'success');
            onProctoringConditionsMetCallback();
        }
        hideProctoringFullscreenPrompt();
        if (startAssessmentOverlay) {
            startAssessmentOverlay.classList.add('hidden');
        }
    } else {
        if (proctoringDetectionActive) {
            proctoringDetectionActive = false;
            proctoringContext.clearRect(0, 0, proctoringCanvasElement.width, proctoringCanvasElement.height);
            stopIdleDetection();
        }
        onProctoringConditionsViolatedCallback();

        if (!isFullscreen) {
            updateProctoringStatus("Please enter fullscreen mode to start/resume the assessment.", 'info');
            showProctoringFullscreenPrompt();
            startFullscreenCountdown();
            if (startAssessmentOverlay) {
                startAssessmentOverlay.classList.add('hidden');
            }
        } else if (!isTabVisible) {
            // This is the crucial part for tab switches
            // Increment count directly when tab becomes hidden
            tabSwitchCount++;
            sendProctoringLogCallback('tab_switch', { count: tabSwitchCount, max: MAX_TAB_SWITCHES });
            updateProctoringStatus(`You left the assessment tab (${tabSwitchCount}/${MAX_TAB_SWITCHES}). Please stay on this tab.`, 'warning');

            if (tabSwitchCount >= MAX_TAB_SWITCHES) {
                const assessmentForm = document.querySelector('form');
                if (assessmentForm) {
                    showProctoringAutoSubmitModal(`Your assessment has been automatically submitted due to excessive tab switches (${MAX_TAB_SWITCHES} times). Please acknowledge to continue.`);
                    assessmentForm.submit();
                    sendProctoringLogCallback('form_submitted', 'Form submitted due to excessive tab switches.');
                } else {
                    triggerProctoringCriticalError("Assessment form not found for submission after excessive tab switches.");
                }
            } else {
                // Always start a grace period for tab switch, allowing it to reset the timer
                startProctoringGracePeriod("You left the assessment tab.");
            }
            if (startAssessmentOverlay) {
                startAssessmentOverlay.classList.add('hidden');
            }
        } else if (!isModelLoaded || !isCameraReady) {
            updateProctoringStatus("Camera/Security model not ready. Please wait or refresh.", 'warning');
            if (!isFullscreen && startAssessmentOverlay) {
                startAssessmentOverlay.classList.remove('hidden');
                if (startAssessmentButton) {
                    startAssessmentButton.disabled = true;
                }
            } else if (startAssessmentOverlay) {
                startAssessmentOverlay.classList.add('hidden');
            }
        } else {
            updateProctoringStatus("Proctoring conditions not fully met. Please check camera and tab visibility.", 'warning');
            if (startAssessmentOverlay) {
                startAssessmentOverlay.classList.add('hidden');
            }
        }
    }
}

function triggerProctoringCriticalError(message) {
    if (proctoringErrorTriggered) return;
    proctoringErrorTriggered = true;

    proctoringDetectionActive = false;
    // Stop camera stream
    if (proctoringVideoElement && proctoringVideoElement.srcObject) {
        proctoringVideoElement.srcObject.getTracks().forEach(track => track.stop());
    }
    // Stop audio monitoring
    if (audioContext) {
        audioContext.close();
        audioContext = null;
        analyser = null;
        microphoneStream = null;
    }
    // Stop photo capture
    stopPhotoCapture(); // STOP PHOTO CAPTURE HERE

    if (startAssessmentOverlay) {
        startAssessmentOverlay.classList.add('hidden');
    }

    clearProctoringGracePeriod();
    clearFullscreenCountdown();
    hideProctoringFullscreenPrompt();
    stopIdleDetection();

    updateProctoringStatus(`Critical Error: ${message}`, 'error');
    showProctoringErrorModal(`Your assessment session has been terminated. Reason: ${message}`);
    onProctoringCriticalErrorCallback(message);
    sendProctoringLogCallback('critical_error', message);
}

function showProctoringErrorModal(message) {
    proctoringModalErrorMessage.textContent = message;
    proctoringErrorModalOverlay.classList.add('active');
}

function hideProctoringErrorModal() {
    proctoringErrorModalOverlay.classList.remove('active');
}

function showProctoringFullscreenPrompt() {
    proctoringFullscreenPromptOverlay.classList.add('active');
}

function hideProctoringFullscreenPrompt() {
    proctoringFullscreenPromptOverlay.classList.remove('active');
}

async function requestFullscreenAndStartProctoring() {
    if (proctoringErrorTriggered) {
        proctoringShowCustomMessageBox("Assessment Error", "An assessment error has occurred. Please refresh the page to restart.");
        return;
    }

    if (!proctoringInitialized) {
        proctoringInitialized = true;
        sendProctoringLogCallback('assessment_started', 'User clicked Start Assessment button.');
        await startProctoringCamera();
    }

    if (!document.fullscreenElement) {
        try {
            if (startAssessmentOverlay) {
                startAssessmentOverlay.classList.add('hidden');
            }
            await document.documentElement.requestFullscreen();
        } catch (err) {
            console.error("Fullscreen error:", err);
            triggerProctoringCriticalError("Fullscreen mode access denied or failed.");
        }
    }
}

function reEnterFullscreenFromPrompt() {
    hideProctoringFullscreenPrompt();
    document.documentElement.requestFullscreen().then(() => {
        sendProctoringLogCallback('fullscreen_reentered', 'User re-entered fullscreen mode.');
    }).catch(err => {
        triggerProctoringCriticalError(`Failed to re-enter fullscreen: ${err.message}.`);
        console.error("Failed to re-enter fullscreen from prompt:", err);
    });
}

function cancelAssessmentFromFullscreenPrompt() {
    hideProctoringFullscreenPrompt();
    triggerProctoringCriticalError("User canceled the assessment.");
    sendProctoringLogCallback('assessment_canceled', 'User clicked Cancel Assessment button.');
}

function handleFullscreenChange() {
    const headers = document.querySelectorAll('header, .header');
    const footers = document.querySelectorAll('footer, .footer');
    if (document.fullscreenElement) {
        headers.forEach(el => el.style.display = 'none');
        footers.forEach(el => el.style.display = '');
    } else {
        headers.forEach(el => el.style.display = '');
        footers.forEach(el => el.style.display = '');
    }
    updateProctoringConditions();
}

function handleVisibilityChange() {
    // If returning to the tab and a fullscreen countdown was active, clear it.
    if (!document.hidden && isFullscreenCountdownActive) {
        clearFullscreenCountdown();
    }

    // If the tab is hidden
    if (document.hidden) {
        // Increment count and apply logic *immediately* when tab is hidden
        tabSwitchCount++;
        sendProctoringLogCallback('tab_switch', { count: tabSwitchCount, max: MAX_TAB_SWITCHES });
        updateProctoringStatus(`You left the assessment tab (${tabSwitchCount}/${MAX_TAB_SWITCHES}). Please stay on this tab.`, 'warning');

        if (tabSwitchCount >= MAX_TAB_SWITCHES) {
            const assessmentForm = document.querySelector('form');
            if (assessmentForm) {
                showProctoringAutoSubmitModal(`Your assessment has been automatically submitted due to excessive tab switches (${MAX_TAB_SWITCHES} times). Please acknowledge to continue.`);
                assessmentForm.submit();
                sendProctoringLogCallback('form_submitted', 'Form submitted due to excessive tab switches.');
            } else {
                triggerProctoringCriticalError("Assessment form not found for submission after excessive tab switches.");
            }
        } else {
            // Always start a new grace period for tab switch when the tab becomes hidden
            startProctoringGracePeriod("You left the assessment tab.");
        }
        if (startAssessmentOverlay) {
            startAssessmentOverlay.classList.add('hidden');
        }
    } else { // If the tab becomes visible
        // Clear any tab-switch specific grace period if it was active
        if (proctoringGracePeriodReason.includes("You left the assessment tab")) {
            clearProctoringGracePeriod();
        }
    }
    // Always update conditions after visibility change to re-evaluate overall proctoring state
    updateProctoringConditions();
}

let idleDetectionInterval = null;
function recordActivity() {
    lastActivityTimestamp = Date.now();
    if (proctoringGracePeriodReason.includes("No activity")) {
        clearProctoringGracePeriod();
    }
}

function startIdleDetection() {
    if (idleDetectionInterval) clearInterval(idleDetectionInterval);
    idleDetectionInterval = setInterval(() => {
        const currentTime = Date.now();
        if (currentTime - lastActivityTimestamp > INACTIVITY_THRESHOLD_SECONDS * 1000) {
            startProctoringGracePeriod(`No activity detected for ${INACTIVITY_THRESHOLD_SECONDS} seconds.`);
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

/**
 * Starts the periodic photo capture.
 */
function startPhotoCapture() {
    if (proctoringPhotoInterval) {
        clearInterval(proctoringPhotoInterval); // Clear any existing interval
    }
    // Capture first photo immediately, then every PHOTO_CAPTURE_INTERVAL_MS
    capturePhoto();
    proctoringPhotoInterval = setInterval(capturePhoto, PHOTO_CAPTURE_INTERVAL_MS);
    sendProctoringLogCallback('photo_capture_started', `Photo capture initiated every ${PHOTO_CAPTURE_INTERVAL_MS / 1000} seconds.`);
}

/**
 * Stops the periodic photo capture.
 */
function stopPhotoCapture() {
    if (proctoringPhotoInterval) {
        clearInterval(proctoringPhotoInterval);
        proctoringPhotoInterval = null;
        sendProctoringLogCallback('photo_capture_stopped', 'Photo capture stopped.');
    }
}

/**
 * Captures a single photo from the webcam and sends it to the server.
 */
function capturePhoto() {
    if (!proctoringVideoElement || !proctoringCanvasElement || proctoringVideoElement.paused || proctoringVideoElement.ended || !proctoringVideoElement.srcObject) {
        console.warn("Cannot capture photo: Video stream not active.");
        return;
    }

    // Ensure canvas dimensions match video dimensions
    proctoringCanvasElement.width = proctoringVideoElement.videoWidth;
    proctoringCanvasElement.height = proctoringVideoElement.videoHeight;

    // Draw the current video frame onto the canvas
    proctoringContext.drawImage(proctoringVideoElement, 0, 0, proctoringCanvasElement.width, proctoringCanvasElement.height);

    // Get the image data as a Base64 encoded PNG
    const imageData = proctoringCanvasElement.toDataURL('image/png');

    // Send the image data to the server
    sendProctoringPhoto(imageData);
}

/**
 * Sends the captured photo data to the server via Fetch API.
 * @param {string} imageData - Base64 encoded image data (e.g., "data:image/png;base64,...").
 */
async function sendProctoringPhoto(imageData) {
    if (!currentQuizId || !currentAttemptId || !currentUserId || !baseUrl) {
        console.error("Proctoring photo upload failed: Missing quiz, attempt, user ID or base URL.");
        sendProctoringLogCallback('photo_upload_failed', 'Missing IDs or Base URL for photo upload.');
        return;
    }

    try {
        const response = await fetch(`${baseUrl}student/capture_photo.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                quiz_id: currentQuizId,
                attempt_id: currentAttemptId,
                user_id: currentUserId,
                image_data: imageData
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Failed to upload proctoring photo:', response.status, errorText);
            sendProctoringLogCallback('photo_upload_failed', { status: response.status, error: errorText });
        } else {
            const result = await response.json();
            if (result.success) {
                sendProctoringLogCallback('photo_uploaded', `Photo captured and uploaded: ${result.image_path}`);
            } else {
                console.error('Server reported photo upload failed:', result.message);
                sendProctoringLogCallback('photo_upload_failed', { message: result.message });
            }
        }
    } catch (error) {
        console.error('Network error during proctoring photo upload:', error);
        sendProctoringLogCallback('photo_upload_failed', { error: error.message, type: 'network' });
    }
}

window.initProctoring = initProctoring;