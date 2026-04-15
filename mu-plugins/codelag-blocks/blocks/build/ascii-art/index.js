/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/edit.js":
/*!****************************************************************!*\
  !*** ./mu-plugins/codelag-blocks/blocks/src/ascii-art/edit.js ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * ASCII Art — editor component.
 *
 * The art pieces are shipped as a fixed list in block.json's attribute
 * defaults, so authors can't add/remove/edit entries — they only control
 * how often the frontend rotates between them. Preview shows one piece so
 * the author still sees a real render of the block.
 */





function Edit({
  attributes,
  setAttributes
}) {
  const {
    items,
    intervalMs
  } = attributes;
  const pieces = Array.isArray(items) ? items : [];
  const preview = pieces.length > 0 ? pieces[0] : '';
  const count = pieces.length;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'codelag-ascii-art codelag-ascii-art--editor'
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Rotation', 'codelag-blocks'),
        initialOpen: true,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Interval (ms)', 'codelag-blocks'),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('How long each piece stays on screen before a new random piece is picked.', 'codelag-blocks'),
          value: intervalMs,
          min: 500,
          max: 30000,
          step: 250,
          onChange: value => setAttributes({
            intervalMs: typeof value === 'number' ? value : 5000
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
          className: "codelag-ascii-art__count",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(/* translators: %d: number of ascii art pieces bundled with the block. */
          (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('%d pieces bundled with this block.', 'codelag-blocks'), count)
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("pre", {
        className: "codelag-ascii-art__stage",
        "aria-hidden": "true",
        children: preview || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No ASCII art bundled.', 'codelag-blocks')
      })
    })]
  });
}

/***/ }),

/***/ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/index.js":
/*!*****************************************************************!*\
  !*** ./mu-plugins/codelag-blocks/blocks/src/ascii-art/index.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block.json */ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/edit.js");
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./editor.scss */ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/editor.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.scss */ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/style.scss");
/**
 * ASCII Art block registration.
 *
 * Dynamic block — render.php emits a <pre> with a random initial piece and
 * a JSON-serialised list of all pieces in a data-attribute; view.js cycles
 * through them on a timer at render-time.
 */






(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_1__.name, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => null
});

/***/ }),

/***/ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/editor.scss":
/*!********************************************************************!*\
  !*** ./mu-plugins/codelag-blocks/blocks/src/ascii-art/editor.scss ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/style.scss":
/*!*******************************************************************!*\
  !*** ./mu-plugins/codelag-blocks/blocks/src/ascii-art/style.scss ***!
  \*******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./mu-plugins/codelag-blocks/blocks/src/ascii-art/block.json":
/*!*******************************************************************!*\
  !*** ./mu-plugins/codelag-blocks/blocks/src/ascii-art/block.json ***!
  \*******************************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"codelag/ascii-art","version":"0.1.0","title":"ASCII Art","category":"design","icon":"editor-code","description":"Rotates through a list of ASCII art pieces at random, centered in the wrapper.","textdomain":"codelag-blocks","keywords":["ascii","art","text","decoration"],"supports":{"html":false,"anchor":true,"align":["wide","full"],"customClassName":true},"attributes":{"items":{"type":"array","default":[" ┌────────────────────────────────────────────────┐\\n │  ● ● ●                         ~/codelagoon    │\\n ├────────────────────────────────────────────────┤\\n │                                                │\\n │  $ whoami                                      │\\n │  clappo                                        │\\n │                                                │\\n │  $ ls lagoons/                                 │\\n │  snippet-01.php   snippet-02.js   README.md    │\\n │                                                │\\n │  $ cat hello.txt                               │\\n │  > hello, world                                │\\n │                                                │\\n │  $ _                                           │\\n │                                                │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │   ┌────────────────────────────────────────┐   │\\n │   │ ● ● ●                      codelagoon  │   │\\n │   ├────────────────────────────────────────┤   │\\n │   │                                        │   │\\n │   │   ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░        │   │\\n │   │   ░   H E L L O   W O R L D   ░        │   │\\n │   │   ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░        │   │\\n │   │                                        │   │\\n │   │   > _                                  │   │\\n │   │                                        │   │\\n │   └────────────────────────────────────────┘   │\\n │        \\\\\\\\____________________________//        │\\n │           ╰────────────────────────╯           │\\n │         ┌────────────────────────────┐         │\\n │         └────────────────────────────┘         │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │     ┌───────────────┐                          │\\n │     │╔═════════════╗│                          │\\n │     │║             ║│                          │\\n │     │║   PROGRAM   ║│           ) )            │\\n │     │║  LANGUAGES  ║│        .--------.        │\\n │     │║             ║│        |        |]       │\\n │     │║    vol. 1   ║│        |  ~~~~  |        │\\n │     │║             ║│        |  ~~~~  |        │\\n │     │║  by clappo  ║│        |        |        │\\n │     │║             ║│         \\\\      /         │\\n │     │╚═════════════╝│          \'----\'          │\\n │     └───────────────┘     ~~~~~~~~~~~~~~       │\\n │                                                │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │               *                                │\\n │               |                                │\\n │               *──┐                             │\\n │               |   \\\\                            │\\n │               *    *──┐                        │\\n │              /|    |   \\\\                       │\\n │             * *    *    *                      │\\n │             |  \\\\   |     \\\\                     │\\n │             *   .  *      *──┐                 │\\n │            /|      |       \\\\  \\\\                │\\n │           * *      *        *  *               │\\n │           |  \\\\     |         \\\\   \\\\             │\\n │           *   *    .          *   .            │\\n │          / \\\\                   \\\\               │\\n │         *   .                   *              │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │ ┌────────────────────────────────────────────┐ │\\n │ │ ● ● ●   plugin.php — codelagoon            │ │\\n │ ├────────────────────────────────────────────┤ │\\n │ │  1  <?php                                  │ │\\n │ │  2                                         │ │\\n │ │  3  add_action(                            │ │\\n │ │  4      \'init\',                            │ │\\n │ │  5      \'codelag_register_post_types\'      │ │\\n │ │  6  );                                     │ │\\n │ │  7                                         │ │\\n │ │  8  function codelag_register_post_types() │ │\\n │ │  9      register_post_type( \'lagoon\' );    │ │\\n │ │ 10  }                                      │ │\\n │ │ 11                                         │ │\\n │ └────────────────────────────────────────────┘ │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │ ┌────────────────────────────────────────────┐ │\\n │ │ ● ● ●   query.php                          │ │\\n │ ├────────────────────────────────────────────┤ │\\n │ │  1  <?php                                  │ │\\n │ │  2  $q = new WP_Query( array(              │ │\\n │ │  3      \'post_type\'      => \'lagoon\',      │ │\\n │ │  4      \'posts_per_page\' => 5,             │ │\\n │ │  5  ) );                                   │ │\\n │ │  6                                         │ │\\n │ │  7  while ( $q->have_posts() ) {           │ │\\n │ │  8      $q->the_post();                    │ │\\n │ │  9      the_title();                       │ │\\n │ │ 10  }                                      │ │\\n │ │ 11  wp_reset_postdata();                   │ │\\n │ └────────────────────────────────────────────┘ │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │ ┌────────────────────────────────────────────┐ │\\n │ │ ● ● ●   rest-api.php                       │ │\\n │ ├────────────────────────────────────────────┤ │\\n │ │  1  <?php                                  │ │\\n │ │  2  add_action( \'rest_api_init\', function()│ │\\n │ │  3      register_rest_route(               │ │\\n │ │  4          \'codelag/v1\',                  │ │\\n │ │  5          \'/lagoons\',                    │ │\\n │ │  6          array(                         │ │\\n │ │  7              \'methods\'  => \'GET\',       │ │\\n │ │  8              \'callback\' => \'get_lagoons\'│ │\\n │ │  9          )                              │ │\\n │ │ 10      );                                 │ │\\n │ │ 11  } );                                   │ │\\n │ └────────────────────────────────────────────┘ │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │     ┌────────────────────────────────────┐     │\\n │     │ ● ● ●                  codelagoon  │     │\\n │     ├────────────────────────────────────┤     │\\n │     │                                    │     │\\n │     │  $ whoami                          │     │\\n │     │  clappo                            │     │\\n │     │                                    │     │\\n │     │  $ _                               │     │\\n │     │                                    │     │\\n │     └────────────────────────────────────┘     │\\n │   /________________________________________\\\\   │\\n │  /__________________________________________\\\\  │\\n │        \\\\______________________________/        │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │   ┌────────────────────────────────────────┐   │\\n │   │ ○ ○ ○   codelagoon.gq/                 │   │\\n │   ├────────────────────────────────────────┤   │\\n │   │                                        │   │\\n │   │    # Welcome to CodeLagoon             │   │\\n │   │                                        │   │\\n │   │    > share snippets. fork lagoons.     │   │\\n │   │                                        │   │\\n │   │    [ sign in ]    [ browse ]           │   │\\n │   │                                        │   │\\n │   │                                        │   │\\n │   └────────────────────────────────────────┘   │\\n │                                                │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │          ┌──────────────────────────┐          │\\n │          │ web-01          ● ● ●    │          │\\n │          ├──────────────────────────┤          │\\n │          │ web-02          ● ● ○    │          │\\n │          ├──────────────────────────┤          │\\n │          │ db-01           ● ● ●    │          │\\n │          ├──────────────────────────┤          │\\n │          │ cache-01        ● ○ ○    │          │\\n │          ├──────────────────────────┤          │\\n │          │ worker-01       ● ● ●    │          │\\n │          └──────────────────────────┘          │\\n │                                                │\\n │                                                │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │   wp_lagoons                                   │\\n │   ┌────┬────────────────────┬──────────────┐   │\\n │   │ id │ title              │ created      │   │\\n │   ├────┼────────────────────┼──────────────┤   │\\n │   │  1 │ php array tricks   │ 2026-04-10   │   │\\n │   │  2 │ wp_query gotchas   │ 2026-04-11   │   │\\n │   │  3 │ sass patterns      │ 2026-04-12   │   │\\n │   │  4 │ docker compose     │ 2026-04-13   │   │\\n │   │  5 │ rest endpoints     │ 2026-04-14   │   │\\n │   └────┴────────────────────┴──────────────┘   │\\n │                                                │\\n │   5 rows in set (0.00 sec)                     │\\n │                                                │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │   $ git log --oneline                          │\\n │                                                │\\n │   a7f3c2d  feat: add ascii-art block           │\\n │   4672f65  merge: feature/likes                │\\n │   64a0c79  fix: simple lint issues             │\\n │   bae291d  fix: more lint                      │\\n │   cd9b333  chore: house keeping                │\\n │   7f70140  chore: house keeping                │\\n │   3a1b9e4  refactor: extract download handler  │\\n │   9c2f81a  feat: download zips as guest        │\\n │   e5d4a0b  init                                │\\n │                                                │\\n │   $ _                                          │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │   CODELAGOON BIOS v1.0                         │\\n │   (c) 2026 codelagoon inc.                     │\\n │                                                │\\n │   CPU: PHP 8.3 @ 3.14 GHz              [ OK ]  │\\n │   MEM: 2048 MB                         [ OK ]  │\\n │   DISK: /dev/sda (500 GB)              [ OK ]  │\\n │                                                │\\n │   Loading kernel.........................      │\\n │   Mounting filesystems....................     │\\n │   Starting services......................      │\\n │                                                │\\n │   Welcome to CodeLagoon.                       │\\n │   Press any key to continue_                   │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │   ┌──────────┬──────────┬──────────────────┐   │\\n │   │   TODO   │  DOING   │       DONE       │   │\\n │   ├──────────┼──────────┼──────────────────┤   │\\n │   │ * fix    │ * ascii  │ * download zip   │   │\\n │   │   404    │   block  │ * guest prefs    │   │\\n │   │          │          │ * like lagoons   │   │\\n │   │ * add    │          │                  │   │\\n │   │   icons  │          │                  │   │\\n │   │          │          │                  │   │\\n │   │ * rest   │          │                  │   │\\n │   │   tests  │          │                  │   │\\n │   └──────────┴──────────┴──────────────────┘   │\\n │                                                │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │                                                │\\n │                     __                         │\\n │                   _(o )>                       │\\n │                   \\\\ __/                        │\\n │                 ~~~~~~~~~~~                    │\\n │                                                │\\n │        .--------------------------.            │\\n │        |  why isn\'t it working?   |            │\\n │        \'--------------------------\'            │\\n │                                                │\\n │                                                │\\n │                                                │\\n │                                                │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"," ┌────────────────────────────────────────────────┐\\n │                                                │\\n │   ┌────────────────────────────────────────┐   │\\n │   │ ○ ○ ○   codelagoon.gq/missing          │   │\\n │   ├────────────────────────────────────────┤   │\\n │   │                                        │   │\\n │   │                                        │   │\\n │   │           4   0   4                    │   │\\n │   │                                        │   │\\n │   │       page not found                   │   │\\n │   │                                        │   │\\n │   │        [ go home ]                     │   │\\n │   │                                        │   │\\n │   │                                        │   │\\n │   └────────────────────────────────────────┘   │\\n │                                                │\\n │                                                │\\n └────────────────────────────────────────────────┘"],"items":{"type":"string"}},"intervalMs":{"type":"number","default":5000}},"editorScript":"file:./index.js","style":"file:./style-index.css","editorStyle":"file:./index.css","viewScript":"file:./view.js","render":"file:./render.php"}');

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"ascii-art/index": 0,
/******/ 			"ascii-art/style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkcodelagoon"] = globalThis["webpackChunkcodelagoon"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["ascii-art/style-index"], () => (__webpack_require__("./mu-plugins/codelag-blocks/blocks/src/ascii-art/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map