/**
 * interview_engine.js — Phase 4
 * HireIQ Secure AI Interview Session Engine
 *
 * Modules:
 *   1. CameraMonitor   – permission check, preview, MediaPipe hook
 *   2. AudioRecorder   – MediaRecorder-based per-question recording
 *   3. TabMonitor      – document.visibilitychange detection
 *   4. FullscreenCtrl  – requestFullscreen + exit detection
 *   5. IntegritySystem – warning / flag threshold tracking
 *   6. SessionTimer    – countdown timer with auto-submit hook
 *   7. QuestionNav     – question-by-question navigation
 *   8. SessionManager  – orchestrate start, autosave, submit
 */

'use strict';

// ─────────────────────────────────────────────────────────────────
// 1. Camera Monitor
// ─────────────────────────────────────────────────────────────────
const CameraMonitor = (() => {
  let _stream = null;
  let _presenceInterval = null;
  let _attemptId = null;

  /**
   * Request camera access and show preview in the given <video> element.
   * Resolves true on success, false if permission denied or unavailable.
   *
   * MediaPipe Hook:
   *   Replace the presenceInterval callback with MediaPipe FaceMesh
   *   detection in Phase 5. Set faceDetected based on landmark count.
   */
  async function init(videoEl, attemptId) {
    _attemptId = attemptId;
    try {
      _stream = await navigator.mediaDevices.getUserMedia({
        video: { width: 320, height: 240, facingMode: 'user' },
        audio: false,
      });
      if (videoEl) {
        videoEl.srcObject = _stream;
        videoEl.muted = true;
        await videoEl.play().catch(() => {});
      }
      updateCameraStatus(true);
      _startPresenceLogging();
      return true;
    } catch (err) {
      console.warn('[CameraMonitor] Permission denied or unavailable:', err.message);
      updateCameraStatus(false);
      IntegritySystem.recordViolation('CAMERA_DISABLED');
      return false;
    }
  }

  function updateCameraStatus(active) {
    const dot  = document.getElementById('camera-status-dot');
    const text = document.getElementById('camera-status-text');
    if (dot)  dot.style.background  = active ? '#22c55e' : '#ef4444';
    if (text) text.textContent = active ? 'Camera Active' : 'Camera Unavailable';
  }

  /**
   * Phase 4: logs presence every 10 s with face_detected = false
   * (camera is available but no ML model runs yet).
   *
   * Phase 5 MediaPipe Hook:
   *   Replace false with the result of a FaceMesh landmark check:
   *   const results = await mediaPipeFaceMesh.send({ image: videoEl });
   *   const faceDetected = results.multiFaceLandmarks.length > 0;
   */
  function _startPresenceLogging() {
    if (_presenceInterval) clearInterval(_presenceInterval);
    _presenceInterval = setInterval(() => {
      if (!_attemptId) return;
      const cameraActive = _stream && _stream.active;
      const faceDetected = false; // MEDIAPIPE_HOOK: replace in Phase 5

      if (!cameraActive) {
        IntegritySystem.recordViolation('CAMERA_DISABLED');
      }

      _apiPost('log_presence', {
        attempt_id: _attemptId,
        face_detected: faceDetected, // MEDIAPIPE_HOOK: pass real value in Phase 5
      }).catch(() => {});
    }, 10000);
  }

  function stop() {
    clearInterval(_presenceInterval);
    if (_stream) {
      _stream.getTracks().forEach(t => t.stop());
      _stream = null;
    }
  }

  function isActive() {
    return _stream && _stream.active;
  }

  return { init, stop, isActive, updateCameraStatus };
})();


// ─────────────────────────────────────────────────────────────────
// 2. Audio Recorder
// ─────────────────────────────────────────────────────────────────
const AudioRecorder = (() => {
  let _mediaRecorder = null;
  let _chunks = [];
  let _stream = null;
  let _questionStartTime = null;
  let _currentQuestionId = null;
  let _currentAttemptId  = null;
  let _onSaved = null;  // callback(questionId, audioPath)

  async function requestMicPermission() {
    try {
      _stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      return true;
    } catch {
      return false;
    }
  }

  function startRecording(questionId, attemptId, onSaved) {
    if (!_stream) {
      console.error('[AudioRecorder] No mic stream — call requestMicPermission first');
      return false;
    }
    _chunks = [];
    _currentQuestionId = questionId;
    _currentAttemptId  = attemptId;
    _onSaved = onSaved;
    _questionStartTime = Date.now();

    const mimeType = _pickMime();
    _mediaRecorder = new MediaRecorder(_stream, { mimeType });
    _mediaRecorder.ondataavailable = e => { if (e.data.size > 0) _chunks.push(e.data); };
    _mediaRecorder.onstop = _onStop;
    _mediaRecorder.start(1000); // 1-second timeslices for reliable data
    _updateRecordUI(true);
    return true;
  }

  function stopRecording() {
    if (_mediaRecorder && _mediaRecorder.state !== 'inactive') {
      _mediaRecorder.stop();
      _updateRecordUI(false);
    }
  }

  function isRecording() {
    return _mediaRecorder && _mediaRecorder.state === 'recording';
  }

  async function _onStop() {
    const blob = new Blob(_chunks, { type: _mediaRecorder.mimeType || 'audio/webm' });
    const responseTime = Math.round((Date.now() - _questionStartTime) / 1000);

    const form = new FormData();
    form.append('attempt_id',    String(_currentAttemptId));
    form.append('question_id',   String(_currentQuestionId));
    form.append('response_time', String(responseTime));
    form.append('audio_blob',    blob, 'answer.webm');

    try {
      const res = await fetch(_apiUrl('upload_audio'), { method: 'POST', body: form });
      const data = await res.json();
      if (data.ok && _onSaved) {
        _onSaved(_currentQuestionId, data.audio_path);
      }
      _showSavedBadge(data.ok);
    } catch (err) {
      console.error('[AudioRecorder] Upload failed:', err);
      _showSavedBadge(false);
    }
  }

  function _updateRecordUI(recording) {
    const btn = document.getElementById('record-btn');
    const ind = document.getElementById('recording-indicator');
    if (btn) {
      btn.textContent = recording ? '⏹ Stop Recording' : '🎙 Start Recording';
      btn.classList.toggle('btn-danger',   recording);
      btn.classList.toggle('btn-primary',  !recording);
    }
    if (ind) ind.style.display = recording ? 'flex' : 'none';
  }

  function _showSavedBadge(ok) {
    const badge = document.getElementById('audio-saved-badge');
    if (!badge) return;
    badge.textContent = ok ? '✓ Audio saved' : '✗ Save failed';
    badge.className   = ok ? 'badge bg-success' : 'badge bg-danger';
    badge.style.display = 'inline-block';
    setTimeout(() => { badge.style.display = 'none'; }, 3000);
  }

  function _pickMime() {
    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
    return candidates.find(m => MediaRecorder.isTypeSupported(m)) || '';
  }

  return { requestMicPermission, startRecording, stopRecording, isRecording };
})();


// ─────────────────────────────────────────────────────────────────
// 3. Tab Monitor
// ─────────────────────────────────────────────────────────────────
const TabMonitor = (() => {
  let _attemptId = null;
  let _active = false;

  function start(attemptId) {
    _attemptId = attemptId;
    _active = true;
    document.addEventListener('visibilitychange', _handler);
  }

  function stop() {
    _active = false;
    document.removeEventListener('visibilitychange', _handler);
  }

  function _handler() {
    if (!_active) return;
    const hidden = document.visibilityState === 'hidden';
    const type   = hidden ? 'TAB_HIDDEN' : 'TAB_VISIBLE';

    _apiPost('log_tab_switch', { attempt_id: _attemptId, event_type: type }).catch(() => {});

    if (hidden) {
      IntegritySystem.recordViolation('TAB_SWITCH');
    }
  }

  return { start, stop };
})();


// ─────────────────────────────────────────────────────────────────
// 4. Fullscreen Controller
// ─────────────────────────────────────────────────────────────────
const FullscreenCtrl = (() => {
  let _attemptId = null;
  let _active = false;

  function enter() {
    const el = document.documentElement;
    const fn = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen;
    if (fn) fn.call(el).catch(() => {});
  }

  function exit() {
    const fn = document.exitFullscreen || document.webkitExitFullscreen;
    if (fn) fn.call(document).catch(() => {});
  }

  function isFullscreen() {
    return !!(document.fullscreenElement || document.webkitFullscreenElement);
  }

  function start(attemptId) {
    _attemptId = attemptId;
    _active = true;
    document.addEventListener('fullscreenchange', _handler);
    document.addEventListener('webkitfullscreenchange', _handler);
    enter();
  }

  function stop() {
    _active = false;
    document.removeEventListener('fullscreenchange', _handler);
    document.removeEventListener('webkitfullscreenchange', _handler);
  }

  function _handler() {
    if (!_active) return;
    const inFs   = isFullscreen();
    const type   = inFs ? 'FULLSCREEN_ENTER' : 'FULLSCREEN_EXIT';

    _apiPost('log_fullscreen', { attempt_id: _attemptId, event_type: type }).catch(() => {});

    if (!inFs) {
      IntegritySystem.recordViolation('FULLSCREEN_EXIT');
      _showWarningBanner('You have exited fullscreen. Please return to fullscreen mode.');
    } else {
      _hideWarningBanner();
    }
  }

  function _showWarningBanner(msg) {
    let banner = document.getElementById('fs-warning-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'fs-warning-banner';
      banner.style.cssText =
        'position:fixed;top:0;left:0;width:100%;padding:.75rem 1.5rem;' +
        'background:#f59e0b;color:#1a1a1a;font-weight:600;font-size:.9rem;' +
        'z-index:99999;display:flex;align-items:center;justify-content:space-between;';
      document.body.prepend(banner);
    }
    banner.innerHTML = `<span>⚠ ${msg}</span>
      <button onclick="FullscreenCtrl.enter()" style="padding:.3rem .8rem;border:1px solid #1a1a1a;border-radius:6px;background:transparent;font-weight:600;cursor:pointer;">Return to Fullscreen</button>`;
    banner.style.display = 'flex';
  }

  function _hideWarningBanner() {
    const banner = document.getElementById('fs-warning-banner');
    if (banner) banner.style.display = 'none';
  }

  return { enter, exit, isFullscreen, start, stop };
})();

// Make FullscreenCtrl.enter accessible from inline button
window.FullscreenCtrl = FullscreenCtrl;


// ─────────────────────────────────────────────────────────────────
// 5. Integrity System
// ─────────────────────────────────────────────────────────────────
const IntegritySystem = (() => {
  let _violations = 0;
  let _attemptId  = null;

  const THRESHOLDS = { WARNING: 1, SECOND_WARNING: 2, FLAG: 3 };

  function init(attemptId) {
    _attemptId  = attemptId;
    _violations = 0;
  }

  function recordViolation(eventType) {
    _violations++;
    const severity = _violations >= THRESHOLDS.FLAG ? 'flag' : 'warning';

    _apiPost('log_integrity', {
      attempt_id: _attemptId,
      event_type: eventType,
      severity,
    }).catch(() => {});

    if (_violations === THRESHOLDS.WARNING) {
      _showModal(
        '⚠ Warning',
        'This is your first warning. Leaving the interview tab, exiting fullscreen, or disabling your camera is not permitted.',
        'warning',
        1
      );
    } else if (_violations === THRESHOLDS.SECOND_WARNING) {
      _showModal(
        '⚠ Second Warning',
        'This is your second violation. One more violation will flag your interview for admin review.',
        'warning',
        2
      );
    } else if (_violations >= THRESHOLDS.FLAG) {
      _showModal(
        '🚩 Interview Flagged',
        'Your interview has been flagged due to multiple integrity violations. An admin will review your session. You may continue, but all violations are recorded.',
        'danger',
        _violations
      );
    }
  }

  function _showModal(title, body, type, count) {
    let modal = document.getElementById('integrity-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'integrity-modal';
      modal.innerHTML = `
        <div id="integrity-modal-backdrop"
             style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100000;display:flex;align-items:center;justify-content:center;">
          <div style="background:#fff;border-radius:12px;padding:2rem;max-width:420px;width:90%;box-shadow:0 25px 50px rgba(0,0,0,.25);">
            <h4 id="im-title" style="margin:0 0 1rem;font-family:'Space Grotesk',sans-serif;font-weight:700;"></h4>
            <p  id="im-body"  style="color:#555;font-size:.9rem;line-height:1.6;margin:0 0 1.5rem;"></p>
            <div style="display:flex;justify-content:flex-end;">
              <button id="im-close"
                style="padding:.55rem 1.5rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:.875rem;">
                I Understand
              </button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modal);
      document.getElementById('im-close').addEventListener('click', () => {
        modal.style.display = 'none';
        // Re-enter fullscreen after dismissal
        if (!FullscreenCtrl.isFullscreen()) FullscreenCtrl.enter();
      });
    }

    const titleEl = document.getElementById('im-title');
    const bodyEl  = document.getElementById('im-body');
    const closeEl = document.getElementById('im-close');
    const colors  = { warning: '#f59e0b', danger: '#ef4444' };

    titleEl.textContent = title;
    bodyEl.textContent  = body;
    closeEl.style.background = colors[type] || '#f59e0b';
    closeEl.style.color = type === 'danger' ? '#fff' : '#1a1a1a';
    modal.style.display = 'flex';
  }

  function getViolationCount() { return _violations; }

  return { init, recordViolation, getViolationCount };
})();


// ─────────────────────────────────────────────────────────────────
// 6. Session Timer
// ─────────────────────────────────────────────────────────────────
const SessionTimer = (() => {
  let _totalSeconds = 0;
  let _remaining    = 0;
  let _interval     = null;
  let _onExpire     = null;

  function start(durationMinutes, onExpire) {
    _totalSeconds = durationMinutes * 60;
    _remaining    = _totalSeconds;
    _onExpire     = onExpire;
    _tick();
    _interval = setInterval(_tick, 1000);
  }

  function stop() {
    clearInterval(_interval);
  }

  function _tick() {
    _remaining = Math.max(0, _remaining - 1);
    _render();
    if (_remaining <= 0) {
      clearInterval(_interval);
      if (_onExpire) _onExpire();
    }
  }

  function _render() {
    const el = document.getElementById('session-timer');
    if (!el) return;
    const m = Math.floor(_remaining / 60);
    const s = _remaining % 60;
    el.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    el.style.color = _remaining < 300 ? '#ef4444' : '';
    el.style.fontWeight = _remaining < 300 ? '700' : '';
  }

  return { start, stop };
})();


// ─────────────────────────────────────────────────────────────────
// 7. Question Navigator
// ─────────────────────────────────────────────────────────────────
const QuestionNav = (() => {
  let _questions   = [];
  let _currentIdx  = 0;
  let _answeredSet = new Set();
  let _attemptId   = null;

  function init(questions, attemptId) {
    _questions  = questions;
    _attemptId  = attemptId;
    _currentIdx = 0;
    _render();
  }

  function goTo(idx) {
    if (AudioRecorder.isRecording()) AudioRecorder.stopRecording();
    _currentIdx = Math.max(0, Math.min(idx, _questions.length - 1));
    _render();
  }

  function next() { if (_currentIdx < _questions.length - 1) goTo(_currentIdx + 1); }
  function prev() { if (_currentIdx > 0) goTo(_currentIdx - 1); }

  function markAnswered(questionId) {
    _answeredSet.add(questionId);
    _updateProgressDots();
  }

  function _render() {
    const q = _questions[_currentIdx];
    if (!q) return;

    // Question text
    const qText = document.getElementById('question-text');
    if (qText) qText.textContent = q.question_text;

    // Question counter
    const qCounter = document.getElementById('question-counter');
    if (qCounter) qCounter.textContent = `Question ${_currentIdx + 1} of ${_questions.length}`;

    // Difficulty badge
    const diffBadge = document.getElementById('question-difficulty');
    if (diffBadge) {
      diffBadge.textContent = q.difficulty || 'Medium';
      diffBadge.className   = `badge diff-badge--${(q.difficulty || 'medium').toLowerCase()}`;
    }

    // Audio saved badge
    const savedBadge = document.getElementById('audio-saved-badge');
    if (savedBadge) savedBadge.style.display = 'none';

    // Navigation buttons
    const btnPrev   = document.getElementById('btn-prev-question');
    const btnNext   = document.getElementById('btn-next-question');
    const btnSubmit = document.getElementById('btn-submit-interview');
    if (btnPrev)   btnPrev.disabled   = _currentIdx === 0;
    if (btnNext)   btnNext.style.display   = _currentIdx < _questions.length - 1 ? 'inline-flex' : 'none';
    if (btnSubmit) btnSubmit.style.display = _currentIdx === _questions.length - 1 ? 'inline-flex' : 'none';

    _updateProgressDots();
  }

  function _updateProgressDots() {
    const container = document.getElementById('progress-dots');
    if (!container) return;
    container.innerHTML = '';
    _questions.forEach((q, i) => {
      const dot = document.createElement('button');
      dot.className   = 'progress-dot';
      dot.title       = `Question ${i + 1}`;
      dot.textContent = i + 1;
      dot.addEventListener('click', () => goTo(i));
      if (i === _currentIdx)          dot.classList.add('active');
      if (_answeredSet.has(q.id))     dot.classList.add('answered');
      container.appendChild(dot);
    });

    const pct = document.getElementById('progress-pct');
    if (pct) {
      const ans = _answeredSet.size;
      pct.textContent = `${ans} / ${_questions.length} answered`;
      const bar = document.getElementById('progress-bar-fill');
      if (bar) bar.style.width = `${Math.round((ans / _questions.length) * 100)}%`;
    }
  }

  function getCurrentQuestion() { return _questions[_currentIdx] || null; }
  function allAnswered()        { return _answeredSet.size === _questions.length; }

  return { init, goTo, next, prev, markAnswered, getCurrentQuestion, allAnswered };
})();


// ─────────────────────────────────────────────────────────────────
// 8. Session Manager  (orchestrator)
// ─────────────────────────────────────────────────────────────────
const SessionManager = (() => {
  let _attemptId   = null;
  let _interviewId = null;
  let _duration    = 30;
  let _autosaveInt = null;

  /**
   * Called after the pre-flight permission screen passes.
   * Calls the session_actions API to start/resume the attempt,
   * then initialises all monitoring modules.
   */
  async function launch(interviewId, duration, questions) {
    _interviewId = interviewId;
    _duration    = duration;

    // Start attempt via API
    const res = await _apiPost('start_attempt', { interview_id: interviewId });
    if (!res.ok) {
      alert('Failed to start interview: ' + (res.error || 'Unknown error'));
      return;
    }
    if (res.already_completed) {
      alert('You have already completed this interview.');
      window.location.href = BASE_URL + '/candidate/invitations.php';
      return;
    }

    _attemptId = res.attempt.id;

    // Initialise subsystems
    IntegritySystem.init(_attemptId);
    TabMonitor.start(_attemptId);
    FullscreenCtrl.start(_attemptId);
    QuestionNav.init(questions, _attemptId);

    // Camera
    const video = document.getElementById('camera-preview');
    await CameraMonitor.init(video, _attemptId);

    // Audio
    const micOk = await AudioRecorder.requestMicPermission();
    if (!micOk) {
      IntegritySystem.recordViolation('CAMERA_DISABLED'); // same bucket for mic
    }

    // Timer
    SessionTimer.start(_duration, _onTimeExpired);

    // Wire up record button
    const recordBtn = document.getElementById('record-btn');
    if (recordBtn) {
      recordBtn.addEventListener('click', () => {
        if (AudioRecorder.isRecording()) {
          AudioRecorder.stopRecording();
        } else {
          const q = QuestionNav.getCurrentQuestion();
          if (q) {
            AudioRecorder.startRecording(q.id, _attemptId, (qId) => {
              QuestionNav.markAnswered(qId);
            });
          }
        }
      });
    }

    // Wire nav buttons
    document.getElementById('btn-prev-question')
      ?.addEventListener('click', () => QuestionNav.prev());
    document.getElementById('btn-next-question')
      ?.addEventListener('click', () => QuestionNav.next());
    document.getElementById('btn-submit-interview')
      ?.addEventListener('click', _confirmSubmit);

    // Auto-save: persist answered set every 30 s
    _autosaveInt = setInterval(() => {
      sessionStorage.setItem('hireiq_attempt_id', String(_attemptId));
    }, 30000);
  }

  async function submit() {
    SessionTimer.stop();
    TabMonitor.stop();
    FullscreenCtrl.stop();
    CameraMonitor.stop();
    clearInterval(_autosaveInt);

    if (AudioRecorder.isRecording()) AudioRecorder.stopRecording();
    // Give 1 s for any in-flight audio upload
    await new Promise(r => setTimeout(r, 1000));

    const res = await _apiPost('submit_attempt', { attempt_id: _attemptId });
    if (res.ok) {
      FullscreenCtrl.exit();
      _showSuccessScreen();
    } else {
      alert('Submission failed: ' + (res.error || 'Please try again'));
    }
  }

  function _confirmSubmit() {
    const modal = document.getElementById('confirm-submit-modal');
    if (modal) {
      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
      document.getElementById('btn-confirm-submit')?.addEventListener('click', () => {
        bsModal.hide();
        submit();
      }, { once: true });
    } else {
      if (confirm('Submit this interview? You cannot change answers after submission.')) submit();
    }
  }

  function _onTimeExpired() {
    alert('Time is up! Your interview is being submitted automatically.');
    submit();
  }

  function _showSuccessScreen() {
    document.body.innerHTML = `
      <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8fafc;font-family:'Inter',sans-serif;">
        <div style="text-align:center;padding:3rem;max-width:480px;">
          <div style="width:80px;height:80px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem;">✓</div>
          <h1 style="font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.75rem;color:#0f172a;margin:0 0 .75rem;">Interview Submitted!</h1>
          <p style="color:#64748b;font-size:.95rem;margin:0 0 2rem;line-height:1.6;">
            Thank you for completing the interview. Your responses have been recorded and will be reviewed.
          </p>
          <a href="${BASE_URL}/candidate/dashboard.php"
             style="display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.75rem;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;">
            Return to Dashboard
          </a>
        </div>
      </div>`;
  }

  return { launch, submit };
})();


// ─────────────────────────────────────────────────────────────────
// Internal utilities
// ─────────────────────────────────────────────────────────────────

function _apiUrl(endpoint) {
  return `${BASE_URL}/api/${endpoint}.php`;
}

async function _apiPost(action, payload = {}) {
  try {
    const res = await fetch(_apiUrl('session_actions'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...payload }),
    });
    return await res.json();
  } catch {
    return { ok: false, error: 'Network error' };
  }
}

// Expose for inline HTML access
window.SessionManager  = SessionManager;
window.QuestionNav     = QuestionNav;
window.AudioRecorder   = AudioRecorder;
window.IntegritySystem = IntegritySystem;
