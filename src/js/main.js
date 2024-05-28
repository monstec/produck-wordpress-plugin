/* global produckLib */
/* global M */

export default class initQuackPage {
    constructor(){

        this.initChatJs = initChat;
        this.initQuackJs = initQuack;
        this.linkifyJs = initLinkifyText;

        // @if ENV='production'
        this.log = new produckLib.Log(1);
        // @endif
        // @if ENV!='production'
        this.log = new produckLib.Log(4, "initQuackJs");
        // @endif
    }

    'use strict';

    pageInitialize() {
        this.initChatJs.initialise();
        this.initQuackJs.initialise();
        this.linkifyJs.initialise();
    }
}