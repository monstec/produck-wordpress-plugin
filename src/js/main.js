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
        this.quackJs.initShareContent();
        this.linkifyJs.initialise();
        this.chatJs.initChatJs();
    }
    
    initMaterialize() {
        this.quackJs.initMaterializeInContentBlock();
    }

    initOverviewPagination(totalPages, pageNum) {
        const switchPage = (index) => {
            const currentLocation = window.location.host;   
            const nextLocation = `${window.location.protocol}//${currentLocation}/quacks/${parseInt(index, 10)}/`;            
   
            window.location.replace(nextLocation);
        };
    
        this.quackJs.buildPagination(jQuery('#quacks-overview-container'), totalPages, pageNum, switchPage);
    }    
}