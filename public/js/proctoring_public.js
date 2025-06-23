// public/js/proctoring_public.js
// This script provides basic client-side proctoring features for public quizzes,
// including fullscreen enforcement and camera display (as a sample).

// Expected global variables/functions from the main HTML/PHP file:
// - proctoringViolations: An array to push violation logs.
// - proctoringViolationsInput: The hidden input element to update with violation JSON.
// - window.showCustomMessageBox: A function to display user-friendly messages.
// - window.recordViolation: A function to log specific proctoring events.

// DOM Elements (will be fetched when setupPublicProctoring is called)
let proctoringVideoElement;
let proctoringStatusDisplay;
let proctoringFaceCountDisplay; // Re-purposed for camera status
let startAssessmentOverlay;
let startAssessmentButton;
let proctoringErrorModalOverlay;
let proctoringModalErrorMessage;
let proctoringModalCloseButton;
let proctoringFullscreenPromptOverlay;
let proctoringReEnterFullscreenButton;
let proctoringCancelAssessmentButton;
let fullscreenCountdownText;

// Proctoring State Variables
let proctoringInitialized = false; // Tracks if initial camera/fullscreen setup has run
let proctoringActive = false; // True when all conditions are met (fullscreen, camera, not in grace period)
let proctoringErrorTriggered = false; // True if a critical error occurred

// Fullscreen Grace Period Variables
let fullscreenCountdownTimer = null;
let fullscreenSecondsLeft = 0;
const FULLSCREEN_GRACE_DURATION = 15; // Seconds to re-enter fullscreen before critical error

// Tab Visibility / Focus Grace Period Variables
let tabVisibilityGracePeriodTimer = null;
let tabVisibilityGracePeriodSeconds = 0;
const TAB_VISIBILITY_GRACE_DURATION_SECONDS = 10; // Seconds before critical error for tab switch

// --- Utility Functions (Adapted from your original proctoring.js) ---

function updateProctoringStatus(text, type = 'info') {
    if (!proctoringStatusDisplay) return; // Ensure element exists

    const statusBox = proctoringStatusDisplay.closest('.status-box');
    if (statusBox) {
        statusBox.classList.remove('info', 'success', 'warning', 'error');
        statusBox.classList.add(type);
    }
    proctoringStatusDisplay.textContent = text;
    window.recordViolation('status_update', { message: text, type: type });
}

function showProctoringErrorModal(message) {
    if (!proctoringErrorModalOverlay || !proctoringModalErrorMessage) return;
    proctoringModalErrorMessage.textContent = message;
    proctoringErrorModalOverlay.classList.add('active');
}

function hideProctoringErrorModal() {
    if (!proctoringErrorModalOverlay) return;
    proctoringErrorModalOverlay.classList.remove('active');
}

function showProctoringFullscreenPrompt() {
    if (!proctoringFullscreenPromptOverlay) return;
    proctoringFullscreenPromptOverlay.classList.add('active');
}

function hideProctoringFullscreenPrompt() {
    if (!proctoringFullscreenPromptOverlay) return;
    proctoringFullscreenPromptOverlay.classList.remove('active');
}

/**
 * Triggers a critical proctoring error, stops proctoring, and shows an error message.
 * @param {string} message - The error message to display.
 */
function triggerProctoringCriticalError(message) {
    if (proctoringErrorTriggered) return; // Prevent multiple triggers
    proctoringErrorTriggered = true;

    proctoringActive = false;
    // Stop camera stream if active
    if (proctoringVideoElement && proctoringVideoElement.srcObject) {
        proctoringVideoElement.srcObject.getTracks().forEach(track => track.stop());
    }

    // Clear any timers
    clearFullscreenCountdown();
    clearInterval(tabVisibilityGracePeriodTimer);
    tabVisibilityGracePeriodTimer = null;

    hideProctoringFullscreenPrompt();
    hideProctoringErrorModal(); // Ensure this is hidden before showing the critical one
    updateProctoringStatus(`Critical Error: ${message}`, 'error');
    showProctoringErrorModal(`Your assessment session has been terminated. Reason: ${message}`);
    window.recordViolation('critical_error', { reason: message });

    // Optionally: disable quiz interaction or submit quiz immediately
    // If you have a quiz submission function, you might call it here.
    // e.g., if (typeof window.submitQuizAutomatically === 'function') window.submitQuizAutomatically();
}

// --- Camera & Fullscreen Logic ---

async function startProctoringCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        console.warn('Webcam not supported by this browser.');
        updateProctoringStatus('Webcam not supported by your browser.', 'error');
        triggerProctoringCriticalError("Webcam not supported or access denied.");
        return false;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        proctoringVideoElement.srcObject = stream;
        proctoringVideoElement.onloadedmetadata = () => {
            proctoringVideoElement.play();
            if (proctoringFaceCountDisplay) {
                 proctoringFaceCountDisplay.textContent = "Camera ready.";
            }
            updateProctoringConditions(); // Re-evaluate conditions once camera is ready
        };
        window.recordViolation('camera_started', 'Webcam stream initiated successfully.');
        return true;
    } catch (error) {
        console.error("Error accessing webcam:", error);
        updateProctoringStatus("Camera access denied or failed: " + error.message, 'error');
        triggerProctoringCriticalError("Camera access denied or failed. Please allow camera access and refresh the page.");
        return false;
    }
}

function startFullscreenCountdown() {
    if (isFullscreenCountdownActive) return;

    isFullscreenCountdownActive = true;
    fullscreenSecondsLeft = FULLSCREEN_GRACE_DURATION;
    if (fullscreenCountdownText) {
        fullscreenCountdownText.textContent = `To continue the assessment, you must remain in fullscreen mode. Returning in ${fullscreenSecondsLeft} seconds...`;
    }

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
            if (fullscreenCountdownText) {
                fullscreenCountdownText.textContent = `To continue the assessment, you must remain in fullscreen mode. Returning in ${fullscreenSecondsLeft} seconds...`;
            }
        }
    }, 1000);
    window.recordViolation('fullscreen_countdown_started', { duration: FULLSCREEN_GRACE_DURATION });
}

function clearFullscreenCountdown() {
    if (fullscreenCountdownTimer) {
        clearInterval(fullscreenCountdownTimer);
        fullscreenCountdownTimer = null;
        fullscreenSecondsLeft = 0;
        isFullscreenCountdownActive = false;
        if (fullscreenCountdownText) {
            fullscreenCountdownText.textContent = `To continue the assessment, you must remain in fullscreen mode.`;
        }
        window.recordViolation('fullscreen_countdown_cleared', 'Returned to fullscreen.');
    }
}

function startTabVisibilityGracePeriod(reason) {
    if (proctoringErrorTriggered) return; // Don't start if already in critical error

    // Only start a new grace period if the reason is different or no timer is active
    if (tabVisibilityGracePeriodTimer && reason === "You left the assessment tab.") {
        return; // Already counting down for tab switch
    }

    clearInterval(tabVisibilityGracePeriodTimer); // Clear any existing timer for a new reason

    tabVisibilityGracePeriodSeconds = TAB_VISIBILITY_GRACE_DURATION_SECONDS;
    updateProctoringStatus(`Warning: ${reason} Please correct in ${tabVisibilityGracePeriodSeconds} seconds...`, 'warning');

    tabVisibilityGracePeriodTimer = setInterval(() => {
        tabVisibilityGracePeriodSeconds--;
        if (tabVisibilityGracePeriodSeconds <= 0) {
            clearInterval(tabVisibilityGracePeriodTimer);
            tabVisibilityGracePeriodTimer = null;
            triggerProctoringCriticalError(`${reason} persisted for ${TAB_VISIBILITY_GRACE_DURATION_SECONDS} seconds.`);
        } else {
            updateProctoringStatus(`Warning: ${reason} Please correct in ${tabVisibilityGracePeriodSeconds} seconds...`, 'warning');
        }
    }, 1000);

    window.recordViolation('grace_period_started', { reason: reason, duration: TAB_VISIBILITY_GRACE_DURATION_SECONDS });
}

function clearTabVisibilityGracePeriod() {
    if (tabVisibilityGracePeriodTimer) {
        clearInterval(tabVisibilityGracePeriodTimer);
        tabVisibilityGracePeriodTimer = null;
        tabVisibilityGracePeriodSeconds = 0;
        updateProctoringConditions(); // Re-evaluate conditions
        window.recordViolation('grace_period_cleared', 'Tab visibility violation corrected.');
    }
}


/**
 * Updates the overall proctoring conditions and state.
 * This is the central function that determines if proctoring is 'active'.
 */
function updateProctoringConditions() {
    if (proctoringErrorTriggered) return;

    const isFullscreen = document.fullscreenElement;
    const isTabVisible = !document.hidden;
    const isCameraReady = proctoringVideoElement && proctoringVideoElement.srcObject && proctoringVideoElement.readyState >= 2;

    if (isFullscreen && isTabVisible && isCameraReady && !tabVisibilityGracePeriodTimer && !isFullscreenCountdownActive) {
        // All conditions met, proctoring is active
        clearFullscreenCountdown();
        clearTabVisibilityGracePeriod(); // Ensure tab grace period is cleared if conditions are met

        if (!proctoringActive) {
            proctoringActive = true;
            updateProctoringStatus("All conditions met. Proctoring active.", 'success');
            window.recordViolation('proctoring_started_or_resumed', 'All proctoring conditions are met.');
            // Hide the initial start overlay and fullscreen prompt if active
            if (startAssessmentOverlay) startAssessmentOverlay.classList.add('hidden');
            hideProctoringFullscreenPrompt();
        } else {
             // Already active, just maintain status
             updateProctoringStatus("All conditions met. Proctoring active.", 'success');
        }

    } else {
        // Conditions are violated, proctoring is not active
        if (proctoringActive) {
            proctoringActive = false;
            window.recordViolation('proctoring_paused', 'Proctoring conditions are not met.');
        }

        if (!isFullscreen) {
            updateProctoringStatus("Please enter fullscreen mode to continue the assessment.", 'info');
            showProctoringFullscreenPrompt();
            startFullscreenCountdown();
        } else if (!isTabVisible) {
            updateProctoringStatus("You left the assessment tab. Please return to continue.", 'warning');
            startTabVisibilityGracePeriod("You left the assessment tab.");
        } else if (!isCameraReady) {
            updateProctoringStatus("Camera not ready. Please ensure camera access.", 'warning');
            // This case should ideally be caught by triggerProctoringCriticalError in startProctoringCamera()
            // if camera fails. This is more for a temporary disconnect.
            window.recordViolation('camera_unavailable', 'Camera stream is not active.');
        } else {
            updateProctoringStatus("Proctoring conditions not fully met. Check status messages.", 'warning');
        }
    }
}

async function requestFullscreenAndStartProctoring() {
    if (proctoringErrorTriggered) {
        window.showCustomMessageBox("Assessment Error", "An assessment error has occurred. Please refresh the page to restart.");
        return;
    }

    if (!proctoringInitialized) {
        proctoringInitialized = true;
        window.recordViolation('assessment_started', 'User clicked Start Assessment button.');
        const cameraStarted = await startProctoringCamera();
        if (!cameraStarted) {
            return; // Critical error already triggered
        }
    }

    if (!document.fullscreenElement) {
        try {
            if (startAssessmentOverlay) {
                startAssessmentOverlay.classList.add('hidden'); // Hide the start overlay
            }
            await document.documentElement.requestFullscreen();
            // FullscreenChange event handler will call updateProctoringConditions()
        } catch (err) {
            console.error("Fullscreen error:", err);
            triggerProctoringCriticalError("Fullscreen mode access denied or failed.");
        }
    } else {
        updateProctoringConditions(); // If already fullscreen, just update conditions
    }
}

function reEnterFullscreenFromPrompt() {
    hideProctoringFullscreenPrompt();
    document.documentElement.requestFullscreen().then(() => {
        window.recordViolation('fullscreen_reentered', 'User re-entered fullscreen mode.');
    }).catch(err => {
        triggerProctoringCriticalError(`Failed to re-enter fullscreen: ${err.message}.`);
        console.error("Failed to re-enter fullscreen from prompt:", err);
    });
}

function cancelAssessmentFromFullscreenPrompt() {
    hideProctoringFullscreenPrompt();
    triggerProctoringCriticalError("User canceled the assessment.");
    window.recordViolation('assessment_canceled', 'User clicked Cancel Assessment button.');
}

function handleFullscreenChange() {
    // Hide/show headers/footers when entering/exiting fullscreen (optional, depends on layout)
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
    if (!document.hidden) { // Tab became visible
        clearTabVisibilityGracePeriod(); // Clear any ongoing grace period if user returns
        // If fullscreen prompt is active and user returned to tab, reset fullscreen countdown too.
        if (isFullscreenCountdownActive) {
            clearFullscreenCountdown(); // Reset or clear if they returned to fullscreen
            updateProctoringConditions(); // Re-evaluate everything
        }
    }
    updateProctoringConditions();
}


// --- Basic Proctoring Features (from previous proctoring_public.js) ---

function setupPublicFocusDetection() {
    let hidden, visibilityChange;
    if (typeof document.hidden !== "undefined") {
        hidden = "hidden";
        visibilityChange = "visibilitychange";
    } else if (typeof document.msHidden !== "undefined") {
        hidden = "msHidden";
        visibilityChange = "msvisibilitychange";
    } else if (typeof document.webkitHidden !== "undefined") {
        hidden = "webkitHidden";
        visibilityChange = "webkitvisibilitychange";
    }

    document.addEventListener(visibilityChange, handleVisibilityChange, false);

    window.addEventListener('blur', () => {
        if (!proctoringErrorTriggered) { // Only log if no critical error already
            window.recordViolation('window_blurred');
            startTabVisibilityGracePeriod('You left the quiz window.'); // Use existing grace period
        }
    });

    window.addEventListener('focus', () => {
        if (!proctoringErrorTriggered) { // Only clear if no critical error already
            clearTabVisibilityGracePeriod(); // User returned to window
        }
    });

    console.log("Public proctoring: Focus detection set up.");
}

function setupPublicCopyPasteRightClickDetection() {
    const quizContentArea = document.getElementById('question-container');

    if (quizContentArea) {
        quizContentArea.addEventListener('copy', (e) => {
            e.preventDefault();
            window.recordViolation('copy_attempt', { selectedText: window.getSelection().toString().substring(0, 100) });
            window.showCustomMessageBox('Action Blocked', 'Copying content is not allowed during the quiz.');
        });

        quizContentArea.addEventListener('cut', (e) => {
            e.preventDefault();
            window.recordViolation('cut_attempt', { selectedText: window.getSelection().toString().substring(0, 100) });
            window.showCustomMessageBox('Action Blocked', 'Cutting content is not allowed during the quiz.');
        });

        quizContentArea.addEventListener('paste', (e) => {
            e.preventDefault();
            window.recordViolation('paste_attempt');
            window.showCustomMessageBox('Action Blocked', 'Pasting content is not allowed during the quiz.');
        });

        quizContentArea.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            window.recordViolation('right_click_attempt');
            window.showCustomMessageBox('Action Blocked', 'Right-click is disabled during the quiz.');
        });
        console.log("Public proctoring: Copy/paste and right-click detection set up.");
    } else {
        console.warn("Public proctoring: Quiz content area for copy/paste detection not found (ID: question-container).");
    }
}

// --- Main Initialization Function ---

window.setupPublicProctoring = function() {
    // Get DOM elements
    proctoringVideoElement = document.getElementById('proctoringVideo');
    proctoringStatusDisplay = document.getElementById('proctoringStatusText');
    proctoringFaceCountDisplay = document.getElementById('faceCountDisplay'); // Can be used for camera status
    startAssessmentOverlay = document.getElementById('startAssessmentOverlay');
    startAssessmentButton = document.getElementById('startAssessmentButton');
    proctoringErrorModalOverlay = document.getElementById('proctoringErrorModalOverlay');
    proctoringModalErrorMessage = document.getElementById('proctoringModalErrorMessage');
    proctoringModalCloseButton = document.getElementById('proctoringModalCloseButton');
    proctoringFullscreenPromptOverlay = document.getElementById('proctoringFullscreenPromptOverlay');
    proctoringReEnterFullscreenButton = document.getElementById('proctoringReEnterFullscreenButton');
    proctoringCancelAssessmentButton = document.getElementById('proctoringCancelAssessmentButton');
    fullscreenCountdownText = document.getElementById('fullscreenCountdownText');

    // Add event listeners for the new features
    startAssessmentButton?.addEventListener('click', requestFullscreenAndStartProctoring);
    proctoringModalCloseButton?.addEventListener('click', hideProctoringErrorModal);
    proctoringReEnterFullscreenButton?.addEventListener('click', reEnterFullscreenFromPrompt);
    proctoringCancelAssessmentButton?.addEventListener('click', cancelAssessmentFromFullscreenPrompt);
    document.addEventListener('fullscreenchange', handleFullscreenChange);

    // Initial status update
    updateProctoringStatus("Click 'Start Assessment' to begin.", 'info');

    // Enable the start button now that the script is loaded
    if (startAssessmentButton) {
        startAssessmentButton.disabled = false;
    }

    // Set up basic proctoring features (focus, copy/paste)
    setupPublicFocusDetection();
    setupPublicCopyPasteRightClickDetection();

    console.log("Public proctoring system initialized. Waiting for user to start assessment.");
};

window.stopPublicProctoring = function() {
    if (proctoringVideoElement && proctoringVideoElement.srcObject) {
        proctoringVideoElement.srcObject.getTracks().forEach(track => track.stop());
    }
    clearInterval(fullscreenCountdownTimer);
    clearInterval(tabVisibilityGracePeriodTimer);
    // Note: Event listeners for fullscreen/visibility/copy-paste remain active
    // until page unload, as their removal is more complex than the benefit.
    console.log("Public proctoring components stopped.");
};