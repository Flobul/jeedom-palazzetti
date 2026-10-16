import globals from "globals";
export default [
  { ignores: ["**/vendor/**", "**/node_modules/**", "**/3rdparty/**", "**/third_party/**", "**/*.min.js", "tests/**", "jeedom-core/**"] },
  {
    files: ["core/**/*.js", "desktop/**/*.js", "mobile/**/*.js", "plugin_info/**/*.js"],
    languageOptions: { ecmaVersion: 2022, sourceType: "script", globals: { ...globals.browser, ...globals.es2021, jeedom: "readonly", jeedomUtils: "readonly", jeeDialog: "readonly", jeeFrontEnd: "readonly", domUtils: "readonly", Sortable: "readonly", handleAjaxError: "readonly", isset: "readonly", init: "readonly", is_object: "readonly", is_numeric: "readonly" } },
    rules: { "no-undef": "warn", "no-redeclare": "warn", "no-unreachable": "error", "no-dupe-keys": "error", "no-func-assign": "error" },
  },
];
