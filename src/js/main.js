/* global produckLib */
/* global M */

import i18next from 'i18next';

export default class InitQuackPage {
    constructor(){

        this.chatJs = new produckLib.InitChat();
        this.quackJs = new produckLib.InitQuack();
        this.linkifyJs = new produckLib.LinkifyText();
    }

    'use strict';

    pageInitialize() {
        this.chatJs.initChatJs();
        this.quackJs.initialise();
        this.linkifyJs.initialise();
    }
}