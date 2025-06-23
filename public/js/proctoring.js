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

// New variables for additional proctoring features
let lastCameraActivityTimestamp = Date.now();
const CAMERA_INACTIVITY_THRESHOLD_MS = 5000; // 5 seconds
let audioContext = null;
let analyser = null;
let microphoneStream = null;
const AUDIO_VOLUME_THRESHOLD = 0.1; // Adjust as needed, based on normalized volume
let lastActivityTimestamp = Date.now();
const INACTIVITY_THRESHOLD_SECONDS = 60; // 60 seconds of no mouse/keyboard activity

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

function initProctoring(callbacks) {
    if (callbacks.onConditionsMet) onProctoringConditionsMetCallback = callbacks.onConditionsMet;
    if (callbacks.onConditionsViolated) onProctoringConditionsViolatedCallback = callbacks.onConditionsViolated;
    if (callbacks.onCriticalError) onProctoringCriticalErrorCallback = callbacks.onCriticalError;
    if (callbacks.sendProctoringLog) sendProctoringLogCallback = callbacks.sendProctoringLog;

    proctoringVideoElement = document.getElementById('proctoringVideo');
    proctoringCanvasElement = document.getElementById('proctoringCanvas');
    proctoringContext = proctoringCanvasElement.getContext('2d');

    startAssessmentButton?.addEventListener('click', requestFullscreenAndStartProctoring);
    proctoringModalCloseButton?.addEventListener('click', hideProctoringErrorModal);
    proctoringReEnterFullscreenButton?.addEventListener('click', reEnterFullscreenFromPrompt);
    proctoringCancelAssessmentButton?.addEventListener('click', cancelAssessmentFromFullscreenPrompt);
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
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: true }); // Request audio for audio detection
        proctoringVideoElement.srcObject = stream;
        proctoringVideoElement.onloadedmetadata = () => {
            proctoringCanvasElement.width = proctoringVideoElement.videoWidth;
            proctoringCanvasElement.height = proctoringVideoElement.videoHeight;
            proctoringFaceCountDisplay.textContent = "Camera ready.";
            proctoringVideoElement.play();
            lastCameraActivityTimestamp = Date.now(); // Initialize camera activity timestamp
            startAudioMonitoring(stream); // Start audio monitoring
            updateProctoringConditions();
        };
        sendProctoringLogCallback('camera_started', 'Webcam stream initiated successfully.');
    } catch (error) {
        console.error("Error accessing webcam:", error);
        updateProctoringStatus("Camera access denied or failed: " + error.message, 'error');
        triggerProctoringCriticalError("Camera access denied or failed. Please allow camera access and refresh the page.");
    }
}

function startAudioMonitoring(stream) {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (!analyser) {
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 256; // Smaller FFT size for quicker analysis
    }

    if (microphoneStream) { // Stop existing stream if any
        microphoneStream.getTracks().forEach(track => track.stop());
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
        const normalizedVolume = average / 255; // Normalize to 0-1

        if (normalizedVolume > AUDIO_VOLUME_THRESHOLD) {
            // console.log("High audio detected:", normalizedVolume);
            // Optionally, implement a grace period for audio anomalies
            // startProctoringGracePeriod("Unusual audio detected. Please ensure a quiet environment.");
            sendProctoringLogCallback('audio_anomaly', { volume: normalizedVolume, timestamp: Date.now() });
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

        // Webcam Tampering Detection
        if (proctoringVideoElement.videoWidth === 0 || proctoringVideoElement.videoHeight === 0 || proctoringVideoElement.paused || proctoringVideoElement.ended) {
            startProctoringGracePeriod("Webcam stream is unavailable or paused.");
            sendProctoringLogCallback('webcam_tampering', 'Webcam stream is not active.');
        } else {
            // Check if there's actual video data flowing (simple check, can be improved)
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
            clearProctoringGracePeriod(); // Clear grace period if conditions are met
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
                    // Basic Head Pose Estimation (simplified, more complex with pose models)
                    // For example, checking if nose landmark is far from center of face bounding box
                    const nose = prediction.landmarks[2]; // Assuming landmark[2] is roughly the nose
                    const faceCenterX = start[0] + size[0] / 2;
                    const headTurnThreshold = 50; // Pixels
                    if (Math.abs(nose[0] - faceCenterX) > headTurnThreshold) {
                        // startProctoringGracePeriod("Excessive head movement detected (looking away).");
                        sendProctoringLogCallback('head_pose_anomaly', 'User is likely looking away from the screen.');
                    }
                }
            });
        } else {
            proctoringFaceCountDisplay.textContent = `${predictions.length} faces detected (${proctoringGracePeriodSeconds}s)`;
            startProctoringGracePeriod("More than one face detected. Only one person is allowed.");
        }

        // Multiple Monitor Detection (simplified, relies on screen properties)
        if (window.screen.width > window.innerWidth || window.screen.height > window.innerHeight) {
            // This is a rough heuristic. A more robust solution might involve OS-level APIs or browser extensions.
            // For web, this mostly indicates if the browser window isn't maximized on a single screen.
            // It could also be triggered by non-maximized windows.
            // A more direct way would be to check `screen.availWidth` and `screen.availHeight` against `window.outerWidth` and `window.outerHeight`.
            // For now, let's keep it simple as an indicator.
            // startProctoringGracePeriod("Multiple monitors or unusual display setup detected.");
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

    // Only start a new grace period if the reason is different or no timer is active
    if (proctoringGracePeriodTimer && proctoringGracePeriodReason === reason) {
        return;
    }

    clearProctoringGracePeriod(); // Clear any existing timer for a new reason

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
        proctoringGracePeriodReason = "";
        updateProctoringConditions();
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
            detectFaces(); // Start detection loop
            sendProctoringLogCallback('proctoring_started_or_resumed', 'All proctoring conditions are met.');
            // Start idle detection when proctoring is active
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
            // Stop idle detection when proctoring is not active
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
            updateProctoringStatus("You left the assessment tab. Please return to continue.", 'warning');
            startProctoringGracePeriod("You left the assessment tab.");
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
    if (proctoringVideoElement && proctoringVideoElement.srcObject) {
        proctoringVideoElement.srcObject.getTracks().forEach(track => track.stop());
    }
    // Stop audio context if active
    if (audioContext) {
        audioContext.close();
        audioContext = null;
        analyser = null;
        microphoneStream = null;
    }
    if (startAssessmentOverlay) {
        startAssessmentOverlay.classList.add('hidden');
    }

    clearProctoringGracePeriod();
    clearFullscreenCountdown();
    hideProctoringFullscreenPrompt();
    stopIdleDetection(); // Stop idle detection

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
        footers.forEach(el => el.style.display = 'none');
    } else {
        headers.forEach(el => el.style.display = '');
        footers.forEach(el => el.style.display = '');
    }
    updateProctoringConditions();
}

function handleVisibilityChange() {
    if (!document.hidden && isFullscreenCountdownActive) {
        clearFullscreenCountdown();
    }
    updateProctoringConditions();
}

// NEW FEATURE: Activity Detection (Idle)
let idleDetectionInterval = null;
function recordActivity() {
    lastActivityTimestamp = Date.now();
    // console.log("Activity recorded:", lastActivityTimestamp);
    // If a grace period for inactivity was active, clear it
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
    }, 5000); // Check every 5 seconds
}

function stopIdleDetection() {
    if (idleDetectionInterval) {
        clearInterval(idleDetectionInterval);
        idleDetectionInterval = null;
    }
}

// NEW FEATURE: Copy/Paste Prevention (Conceptual for web)
function handleCopyAttempt(event) {
    // Prevent default copy behavior if desired for high-security exams.
    // However, this can be circumvented, and it's generally better to flag.
    // event.preventDefault();
    // proctoringShowCustomMessageBox("Copying Not Allowed", "Copying content from the assessment is not permitted.");
    sendProctoringLogCallback('copy_attempt', 'User attempted to copy content.');
    startProctoringGracePeriod("Copying content is not allowed.");
}


window.initProctoring = initProctoring;