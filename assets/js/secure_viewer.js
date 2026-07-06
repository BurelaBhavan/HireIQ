/**
 * secure_viewer.js — Enterprise Document Security Layer
 * AI Interview Assessment Platform — Phase 4.5
 *
 * Protections:
 *   1.  Right-click block
 *   2.  Keyboard shortcut blocks (Ctrl+C/S/P/A/U, F12, etc.)
 *   3.  Text selection disable
 *   4.  Drag block
 *   5.  Copy / Cut / Paste event block
 *   6.  Print block (window.print override + beforeprint)
 *   7.  Tab-switch / visibility blur
 *   8.  Window focus/blur blur
 *   9.  Fullscreen enforcement + exit detection
 *   10. DevTools detection (resize + debugger timing)
 *   11. Canvas watermark (moving timestamp, diagonal repeat)
 *   12. Session heartbeat (30s AJAX ping → server)
 *   13. View timer
 *   14. Violation counter & toast display
 *   15. Screen share / display capture detection (best-effort)
 */

(function () {
  'use strict';

  /* ─────────────────────────────────────────────────────────
     State
  ───────────────────────────────────────────────────────── */
  const V = window.__VIEWER__ || {};
  const state = {
    violations:      0,
    tabSwitches:     0,
    fsExits:         0,
    copyAttempts:    0,
    printAttempts:   0,
    rightClicks:     0,
    devtoolsOpen:    false,
    devtoolsCount:   0,
    screenshotEvents:0,
    viewStart:       Date.now(),
    isFullscreen:    false,
    sessionAlive:    true,
    pendingCounters: {},   // batch counters sent on next heartbeat
    heartbeatTimer:  null,
    viewTimerInterval: null,
    watermarkFrame:  null,
    lastWidth:       window.outerWidth,
    lastHeight:      window.outerHeight,
    devtoolsThreshold: 160,  // px difference for DevTools side-panel
  };

  /* ─────────────────────────────────────────────────────────
     DOM refs
  ───────────────────────────────────────────────────────── */
  const els = {
    blurOverlay:     document.getElementById('blur-overlay'),
    fsOverlay:       document.getElementById('fullscreen-overlay'),
    devOverlay:      document.getElementById('devtools-overlay'),
    sessionOverlay:  document.getElementById('session-overlay'),
    toast:           document.getElementById('violation-toast'),
    toastMsg:        document.getElementById('violation-toast-msg'),
    violationCount:  document.getElementById('violation-count'),
    viewTimer:       document.getElementById('view-timer'),
    statusDot:       document.getElementById('status-dot'),
    statusText:      document.getElementById('status-text'),
    fsExitMsg:       document.getElementById('fs-exit-count-msg'),
    watermark:       document.getElementById('watermark-canvas'),
  };

  /* ─────────────────────────────────────────────────────────
     Toast notification
  ───────────────────────────────────────────────────────── */
  let toastTimer = null;
  function showToast(msg) {
    if (!els.toast || !els.toastMsg) return;
    els.toastMsg.textContent = msg;
    els.toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => els.toast.classList.remove('show'), 3000);
  }

  /* ─────────────────────────────────────────────────────────
     Violation tracking
  ───────────────────────────────────────────────────────── */
  function recordViolation(type, detail, message) {
    state.violations++;
    if (els.violationCount) els.violationCount.textContent = state.violations;

    // Batch for heartbeat
    state.pendingCounters[type] = (state.pendingCounters[type] || 0) + 1;

    // Track per-category
    switch (type) {
      case 'copy_attempt':   state.copyAttempts++;    break;
      case 'print_attempt':  state.printAttempts++;   break;
      case 'right_click':    state.rightClicks++;     break;
      case 'tab_switch':     state.tabSwitches++;     break;
      case 'fullscreen_exit':state.fsExits++;         break;
      case 'devtools_open':  state.devtoolsCount++;   break;
      case 'screenshot_suspicion': state.screenshotEvents++; break;
    }

    showToast(message || 'Action blocked: This event has been logged.');

    // Fire-and-forget AJAX log
    logEvent(type, detail);
  }

  /* ─────────────────────────────────────────────────────────
     AJAX logger
  ───────────────────────────────────────────────────────── */
  function logEvent(eventType, detail, extra) {
    if (!V.baseUrl) return;
    const body = new URLSearchParams({
      action:      'violation',
      doc_id:      V.docId    || 0,
      log_id:      V.logId    || 0,
      event_type:  eventType,
      event_detail: detail   || '',
    });
    fetch(V.baseUrl + '/api/log_activity.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body.toString(),
    }).catch(() => {});
  }

  function sendHeartbeat() {
    if (!V.baseUrl || !state.sessionAlive) return;
    const elapsed = Math.round((Date.now() - state.viewStart) / 1000);
    const body = new URLSearchParams({
      action:          'heartbeat',
      doc_id:          V.docId  || 0,
      log_id:          V.logId  || 0,
      duration:        elapsed,
      tab_switch:      state.pendingCounters['tab_switch']      || 0,
      fullscreen_exit: state.pendingCounters['fullscreen_exit'] || 0,
      copy_attempt:    state.pendingCounters['copy_attempt']    || 0,
      print_attempt:   state.pendingCounters['print_attempt']   || 0,
      right_click:     state.pendingCounters['right_click']     || 0,
      devtools:        state.pendingCounters['devtools_open']   || 0,
      screenshot:      state.pendingCounters['screenshot_suspicion'] || 0,
    });
    state.pendingCounters = {}; // reset batch

    fetch(V.baseUrl + '/api/log_activity.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body.toString(),
    }).then(r => {
      if (r.status === 401) {
        // Session expired
        state.sessionAlive = false;
        showSessionExpired();
      }
    }).catch(() => {});
  }

  /* ─────────────────────────────────────────────────────────
     1.  Right-click block
  ───────────────────────────────────────────────────────── */
  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
    e.stopPropagation();
    recordViolation('right_click', 'contextmenu', 'Right-click is disabled in secure viewer.');
    return false;
  }, true);

  /* ─────────────────────────────────────────────────────────
     2.  Keyboard shortcut blocks
  ───────────────────────────────────────────────────────── */
  const blockedKeys = new Map([
    // [key, ctrl, shift, alt] → [eventType, message]
    ['c',     true,  false, false, 'copy_attempt',    'Copying is prohibited.'],
    ['a',     true,  false, false, 'keyboard_shortcut','Select-all is disabled.'],
    ['s',     true,  false, false, 'keyboard_shortcut','Saving is prohibited.'],
    ['p',     true,  false, false, 'print_attempt',   'Printing is prohibited.'],
    ['s',     true,  true,  false, 'keyboard_shortcut','Save-as is prohibited.'],
    ['u',     true,  false, false, 'keyboard_shortcut','View Source is prohibited.'],
    ['i',     true,  true,  false, 'devtools_open',   'Developer tools are not permitted.'],
    ['j',     true,  true,  false, 'devtools_open',   'Developer tools are not permitted.'],
    ['c',     true,  true,  false, 'devtools_open',   'Developer tools are not permitted.'],
    ['f12',   false, false, false, 'devtools_open',   'Developer tools are not permitted.'],
    ['F12',   false, false, false, 'devtools_open',   'Developer tools are not permitted.'],
  ]);

  document.addEventListener('keydown', function (e) {
    const k     = e.key.toLowerCase();
    const ctrl  = e.ctrlKey || e.metaKey;
    const shift = e.shiftKey;
    const alt   = e.altKey;

    // F12
    if (e.key === 'F12') {
      e.preventDefault(); e.stopPropagation();
      recordViolation('devtools_open', 'F12', 'Developer tools are not permitted.');
      return false;
    }

    // Ctrl+combos
    if (ctrl) {
      switch (k) {
        case 'c':
          e.preventDefault(); e.stopPropagation();
          recordViolation('copy_attempt', 'Ctrl+C', 'Copying is prohibited.');
          return false;
        case 'a':
          e.preventDefault(); e.stopPropagation();
          recordViolation('keyboard_shortcut', 'Ctrl+A', 'Select-all is disabled.');
          return false;
        case 's':
          e.preventDefault(); e.stopPropagation();
          if (shift) recordViolation('keyboard_shortcut', 'Ctrl+Shift+S', 'Save-as is prohibited.');
          else        recordViolation('keyboard_shortcut', 'Ctrl+S', 'Saving is prohibited.');
          return false;
        case 'p':
          e.preventDefault(); e.stopPropagation();
          recordViolation('print_attempt', 'Ctrl+P', 'Printing is prohibited.');
          return false;
        case 'u':
          e.preventDefault(); e.stopPropagation();
          recordViolation('keyboard_shortcut', 'Ctrl+U', 'View Source is prohibited.');
          return false;
      }
      if (shift) {
        switch (k) {
          case 'i': case 'j': case 'c':
            e.preventDefault(); e.stopPropagation();
            recordViolation('devtools_open', 'Ctrl+Shift+' + e.key, 'Developer tools are not permitted.');
            return false;
        }
      }
    }
  }, true);

  /* ─────────────────────────────────────────────────────────
     3 & 4.  Selection + Drag block
  ───────────────────────────────────────────────────────── */
  document.addEventListener('selectstart', e => { e.preventDefault(); return false; }, true);
  document.addEventListener('dragstart',   e => { e.preventDefault(); return false; }, true);

  /* ─────────────────────────────────────────────────────────
     5.  Copy / Cut / Paste
  ───────────────────────────────────────────────────────── */
  document.addEventListener('copy',  e => { e.preventDefault(); recordViolation('copy_attempt',    'copy event',  'Copying is prohibited.');  }, true);
  document.addEventListener('cut',   e => { e.preventDefault(); recordViolation('copy_attempt',    'cut event',   'Cutting is prohibited.');   }, true);
  document.addEventListener('paste', e => { e.preventDefault(); recordViolation('keyboard_shortcut','paste event', 'Pasting is disabled.');    }, true);

  /* ─────────────────────────────────────────────────────────
     6.  Print block
  ───────────────────────────────────────────────────────── */
  window.print = function () {
    recordViolation('print_attempt', 'window.print()', 'Printing is prohibited.');
    return false;
  };

  window.addEventListener('beforeprint', function (e) {
    recordViolation('print_attempt', 'beforeprint event', 'Printing is prohibited.');
    // Hide document content during print attempt
    document.getElementById('doc-frame').style.visibility = 'hidden';
  });
  window.addEventListener('afterprint', function () {
    const frame = document.getElementById('doc-frame');
    if (frame) frame.style.visibility = 'visible';
  });

  /* ─────────────────────────────────────────────────────────
     7 & 8.  Tab switch / Focus/Blur blur overlay
  ───────────────────────────────────────────────────────── */
  function showBlur(reason) {
    if (!els.blurOverlay) return;
    els.blurOverlay.classList.add('active');
    setStatus('warn', 'Document hidden');
    recordViolation('tab_switch', reason, 'Tab switch detected — document hidden.');
  }

  window.restoreViewer = function () {
    if (!els.blurOverlay) return;
    els.blurOverlay.classList.remove('active');
    setStatus('ok', 'Viewing Securely');
  };

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      showBlur('visibilitychange: hidden');
    } else {
      restoreViewer();
    }
  });

  window.addEventListener('blur', function () {
    // Small delay to avoid false positives on page load
    setTimeout(() => {
      if (document.hidden) return;
      showBlur('window blur');
    }, 200);
  });

  window.addEventListener('focus', function () {
    restoreViewer();
  });

  /* ─────────────────────────────────────────────────────────
     9.  Fullscreen enforcement
  ───────────────────────────────────────────────────────── */
  window.requestFullscreen = function () {
    const el = document.documentElement;
    if (el.requestFullscreen)            el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    else if (el.mozRequestFullScreen)    el.mozRequestFullScreen();
    else if (el.msRequestFullscreen)     el.msRequestFullscreen();
  };

  function isInFullscreen() {
    return !!(
      document.fullscreenElement       ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement    ||
      document.msFullscreenElement
    );
  }

  function onFullscreenChange() {
    state.isFullscreen = isInFullscreen();
    if (!state.isFullscreen) {
      // User exited fullscreen
      state.fsExits++;
      recordViolation('fullscreen_exit', 'fullscreenchange', 'Fullscreen exit detected.');
      showFullscreenWarning();
    } else {
      hideFullscreenWarning();
    }
  }

  function showFullscreenWarning() {
    if (!els.fsOverlay) return;
    els.fsOverlay.classList.add('active');
    setStatus('warn', 'Fullscreen required');
    if (els.fsExitMsg) {
      els.fsExitMsg.textContent = `Fullscreen exits so far: ${state.fsExits}`;
    }
  }

  function hideFullscreenWarning() {
    if (els.fsOverlay) els.fsOverlay.classList.remove('active');
    setStatus('ok', 'Viewing Securely');
  }

  document.addEventListener('fullscreenchange',       onFullscreenChange);
  document.addEventListener('webkitfullscreenchange', onFullscreenChange);
  document.addEventListener('mozfullscreenchange',    onFullscreenChange);
  document.addEventListener('MSFullscreenChange',     onFullscreenChange);

  // Request fullscreen on page load (after a brief delay so browser allows it)
  setTimeout(() => {
    requestFullscreen();
    // Don't force-warn immediately; give browser 500ms to enter
    setTimeout(() => {
      if (!isInFullscreen()) showFullscreenWarning();
    }, 800);
  }, 400);

  /* ─────────────────────────────────────────────────────────
     10.  DevTools detection
  ───────────────────────────────────────────────────────── */
  let devtoolsAlertShown = false;

  function checkDevTools() {
    // Method A: window size delta (DevTools undocked or side panel open)
    const widthDiff  = window.outerWidth  - window.innerWidth;
    const heightDiff = window.outerHeight - window.innerHeight;

    if (widthDiff > state.devtoolsThreshold || heightDiff > state.devtoolsThreshold) {
      if (!state.devtoolsOpen) {
        state.devtoolsOpen = true;
        onDevToolsOpen('size-delta');
      }
    } else {
      if (state.devtoolsOpen) {
        state.devtoolsOpen = false;
        if (els.devOverlay) els.devOverlay.classList.remove('active');
        setStatus('ok', 'Viewing Securely');
        if (els.blurOverlay) els.blurOverlay.classList.remove('active');
      }
    }
  }

  function checkDevToolsTiming() {
    // Method B: debugger statement timing — significant delay indicates DevTools paused
    const t0 = performance.now();
    // eslint-disable-next-line no-debugger
    debugger; // If DevTools is open and "Pause on debugger" is active, this will pause
    const t1 = performance.now();
    if (t1 - t0 > 100) {
      onDevToolsOpen('debugger-timing');
    }
  }

  function onDevToolsOpen(method) {
    if (!devtoolsAlertShown) {
      devtoolsAlertShown = true;
      recordViolation('devtools_open', method, 'Developer tools detected — document hidden.');
    }
    if (els.devOverlay)  els.devOverlay.classList.add('active');
    if (els.blurOverlay) els.blurOverlay.classList.remove('active');
    setStatus('danger', 'DevTools Detected');
  }

  setInterval(checkDevTools, 500);
  // Don't run debugger check in a tight loop — only on interval
  setInterval(checkDevToolsTiming, 2000);

  /* ──────────────────────────────────────────────────────
     11.  Canvas watermark — HIGH CONTRAST (visible over white PDFs)
  ────────────────────────────────────────────────────── */
  const canvas = els.watermark;
  let wmTimer = null;

  function drawWatermark() {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    // Resize canvas to viewport
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const now    = new Date();
    const dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    const lines = [
      V.name  || 'Secure User',
      V.email || '',
      `ID: ${V.id || '—'}`,
      '',
      `Viewed: ${dateStr}`,
      timeStr,
      '',
      'HireIQ Secure Viewer',
      'Confidential',
    ];

    ctx.save();
    ctx.rotate(-Math.PI / 5.5); // ~-33 degrees diagonal

    const fontSize    = 14;
    const lineHeight  = 20;
    const blockH      = lines.length * lineHeight + 30;
    const blockW      = 260;
    const stepX       = blockW + 120;
    const stepY       = blockH + 80;

    // Calculate how many tiles we need (accounting for rotation bleed)
    const tilesX = Math.ceil((canvas.width  * 1.5) / stepX) + 2;
    const tilesY = Math.ceil((canvas.height * 1.5) / stepY) + 2;
    const startX = -canvas.width  * 0.3;
    const startY = -canvas.height * 0.3;

    ctx.font         = `${fontSize}px "Inter", sans-serif`;
    ctx.fillStyle    = 'rgba(15, 23, 42, 0.22)';
    ctx.textAlign    = 'left';

    for (let ty = 0; ty < tilesY; ty++) {
      for (let tx = 0; tx < tilesX; tx++) {
        const x = startX + tx * stepX;
        const y = startY + ty * stepY;

        // Draw each line
        lines.forEach((line, i) => {
          if (!line) return;
          const isTitle = (line === 'HireIQ Secure Viewer');
          const isSub   = (line === 'Confidential');
          ctx.font      = isTitle
            ? `700 ${fontSize + 2}px "Space Grotesk", sans-serif`
            : isSub
            ? `600 ${fontSize}px "Inter", sans-serif`
            : `500 ${fontSize}px "Inter", sans-serif`;
          // Draw white shadow first (halo) so text reads on dark AND light bg
          ctx.shadowColor = 'rgba(255,255,255,0.7)';
          ctx.shadowBlur  = 4;
          ctx.fillStyle   = isTitle
            ? 'rgba(37,99,235,0.28)'
            : isSub
            ? 'rgba(220,38,38,0.25)'
            : 'rgba(15,23,42,0.23)';
          ctx.fillText(line, x, y + i * lineHeight);
          ctx.shadowBlur  = 0;
          ctx.shadowColor = 'transparent';
        });
      }
    }

    ctx.restore();

    // Update live CONFIDENTIAL ticker text
    const ticker = document.getElementById('conf-ticker-text');
    if (ticker) {
      const t = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      const d = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
      ticker.innerHTML = `⚠ CONFIDENTIAL &nbsp;|  ${V.name||''} &nbsp;|  ${V.email||''} &nbsp;|  ID:${V.id} &nbsp;|  ${d} ${t} &nbsp;|  HireIQ SECURE — UNAUTHORISED COPY PROHIBITED &nbsp;|  ⚠ CONFIDENTIAL &nbsp;|  ${V.name||''} &nbsp;|  ${V.email||''}`;
    }

    wmTimer = setTimeout(drawWatermark, 1000);
  }

  drawWatermark();
  window.addEventListener('resize', () => {
    clearTimeout(wmTimer);
    drawWatermark();
  });

  /* ─────────────────────────────────────────────────────────
     12.  Session heartbeat
  ───────────────────────────────────────────────────────── */
  state.heartbeatTimer = setInterval(sendHeartbeat, 30000); // every 30s
  sendHeartbeat(); // immediate first ping

  function showSessionExpired() {
    if (els.sessionOverlay) els.sessionOverlay.classList.add('active');
    clearInterval(state.heartbeatTimer);
    clearInterval(state.viewTimerInterval);
  }

  /* ─────────────────────────────────────────────────────────
     13.  View timer
  ───────────────────────────────────────────────────────── */
  function updateViewTimer() {
    const elapsed = Math.round((Date.now() - state.viewStart) / 1000);
    const mm = String(Math.floor(elapsed / 60)).padStart(2, '0');
    const ss = String(elapsed % 60).padStart(2, '0');
    if (els.viewTimer) els.viewTimer.textContent = `${mm}:${ss}`;
  }
  state.viewTimerInterval = setInterval(updateViewTimer, 1000);

  /* ─────────────────────────────────────────────────────────
     14.  Status bar helper
  ───────────────────────────────────────────────────────── */
  function setStatus(level, text) {
    if (!els.statusDot || !els.statusText) return;
    els.statusDot.className  = 'dot';
    if (level === 'warn')   els.statusDot.classList.add('dot--warn');
    if (level === 'danger') els.statusDot.classList.add('dot--danger');
    els.statusText.textContent = text;
  }

  /* ─────────────────────────────────────────────────────────
     15.  Screen share / display capture detection
  ───────────────────────────────────────────────────────── */
  if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
    navigator.mediaDevices.addEventListener('devicechange', function () {
      // If a new capture device appears, log it
      recordViolation('screen_share_detected', 'devicechange event', 'Screen capture device change detected.');
    });
  }

  // Detect if getDisplayMedia is called (monkey-patch)
  if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
    const _orig = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
    navigator.mediaDevices.getDisplayMedia = function (constraints) {
      recordViolation('screen_share_detected', 'getDisplayMedia called', 'Screen sharing attempt detected.');
      state.screenshotEvents++;
      return _orig(constraints);
    };
  }

  /* ─────────────────────────────────────────────────────────
     Close / unload — send final heartbeat
  ───────────────────────────────────────────────────────── */
  window.closeViewer = function (e) {
    sendHeartbeat();
    // Allow navigation to proceed
  };

  window.addEventListener('beforeunload', function () {
    sendHeartbeat();
  });

  /* ─────────────────────────────────────────────────────────
     Expose minimal API for PHP-rendered inline handlers
  ───────────────────────────────────────────────────────── */
  window.__SecureViewer = {
    restoreViewer,
    requestFullscreen,
    recordViolation,
    state,
  };

  /* ─────────────────────────────────────────────────────────
     Disable right-click on the iframe if we can access it
  ───────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    const frame = document.getElementById('doc-frame');
    if (frame) {
      frame.addEventListener('load', () => {
        try {
          const doc = frame.contentDocument || frame.contentWindow?.document;
          if (doc) {
            doc.addEventListener('contextmenu', e => { e.preventDefault(); return false; }, true);
            doc.addEventListener('keydown', e => {
              if ((e.ctrlKey || e.metaKey) && ['c','a','s','p','u'].includes(e.key.toLowerCase())) {
                e.preventDefault();
              }
              if (e.key === 'F12') e.preventDefault();
            }, true);
          }
        } catch (_) {
          // Cross-origin iframe — cannot access (Google Docs Viewer case)
        }
      });
    }
  });

})();
