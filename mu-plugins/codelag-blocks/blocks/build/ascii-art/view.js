/******/ (() => { // webpackBootstrap
/*!****************************************************************!*\
  !*** ./mu-plugins/codelag-blocks/blocks/src/ascii-art/view.js ***!
  \****************************************************************/
/**
 * ASCII Art — frontend rotator.
 *
 * For each `[data-codelag-ascii]` wrapper on the page:
 *  - parses its `data-items` JSON payload (the full list of art pieces)
 *  - on a timer (from `data-interval`) swaps the inner <pre> textContent
 *    with a newly-picked random item — avoiding an immediate repeat when
 *    the list has more than one piece, so the stage actually changes
 *  - wires the optional `[data-codelag-ascii-next]` button to advance to
 *    a new random piece on click (and resets the timer so the user isn't
 *    flipped again 100ms later)
 *
 * Pure vanilla JS, no dependencies. Honours `prefers-reduced-motion`: if
 * the user asks for reduced motion we skip the auto-rotation entirely and
 * leave the server-picked initial frame in place — the Next button still
 * works so the user can step through manually.
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', init);
  function init() {
    const wrappers = document.querySelectorAll('[data-codelag-ascii]');
    if (!wrappers.length) {
      return;
    }
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    wrappers.forEach(function (wrapper) {
      wireWrapper(wrapper, reduceMotion);
    });
  }
  function wireWrapper(wrapper, reduceMotion) {
    const stage = wrapper.querySelector('[data-codelag-ascii-stage]');
    if (!stage) {
      return;
    }
    const items = parseItems(wrapper.getAttribute('data-items'));
    if (items.length < 2) {
      return;
    }
    let interval = parseInt(wrapper.getAttribute('data-interval') || '5000', 10);
    if (isNaN(interval) || interval < 500) {
      interval = 5000;
    }
    const state = {
      index: Math.max(0, items.indexOf(stage.textContent)),
      timer: null
    };
    function advance() {
      state.index = pickNextIndex(items.length, state.index);
      stage.textContent = items[state.index];
    }
    function startTimer() {
      stopTimer();
      state.timer = window.setInterval(advance, interval);
    }
    function stopTimer() {
      if (state.timer !== null) {
        window.clearInterval(state.timer);
        state.timer = null;
      }
    }
    if (!reduceMotion) {
      startTimer();
    }
    const nextBtn = wrapper.querySelector('[data-codelag-ascii-next]');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        advance();
        // Restart the timer so the user doesn't get another flip
        // immediately after clicking.
        if (!reduceMotion) {
          startTimer();
        }
      });
    }
  }

  /**
   * Parse the JSON-encoded items list. Returns an empty array on any
   * parsing failure so a broken attribute can never crash the page.
   *
   * @param {string|null} raw data-items attribute value.
   * @return {string[]} Parsed list (empty on failure).
   */
  function parseItems(raw) {
    if (!raw) {
      return [];
    }
    try {
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        return [];
      }
      return parsed.filter(function (item) {
        return typeof item === 'string' && item.length > 0;
      });
    } catch (err) {
      return [];
    }
  }

  /**
   * Pick a random index in [0, length) that is not `current`. With a
   * single item we'd always return the same one, but the caller guards
   * against that (rotation is skipped for lists of 1).
   *
   * @param {number} length Total items.
   * @param {number} current Current index to avoid.
   * @return {number} New index.
   */
  function pickNextIndex(length, current) {
    if (length <= 1) {
      return 0;
    }
    let next = Math.floor(Math.random() * length);
    if (next === current) {
      next = (next + 1) % length;
    }
    return next;
  }
})();
/******/ })()
;
//# sourceMappingURL=view.js.map