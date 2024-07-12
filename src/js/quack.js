/* global produckLib */
/* global M */
/* global Shariff */

export default class InitQuack {
    constructor(){
        this.lazyload = new produckLib.LazyLoad();

    }

    styleShareShariff(quackRef, title) {
        var buttonsContainer = jQuery('.share-shariff');
        new Shariff(buttonsContainer, {
            orientation: 'horizontal',
            url: quackRef,
            mailUrl: "mailto:?view=mail",
            mailBody: "Ich habe gerade folgende Antwort gefunden, die für dich von Interesse sein könnte: {url}. Schau es dir mal an.",
            lang: "de",
            infoUrl: quackRef,
            title: title,
            services: "[facebook; twitter; instagram; xing; linkedin; mail;]",
            mediaUrl: "/assets/img/ducky_xs.png",
            buttonStyle: "icon",
            theme: "standard",
            referrerTrack: null,
            twitterVia: null
        });
    }

    initShareContent() {
        jQuery(document).on('click', '.share-brand > .share', function (ev) {
            // for quacksSite and quacksOverview get href from current site

            let questionRefDetailSite = '';
            let questionTextDetailSite = '';

            if (jQuery(ev.target).parents('#quacklist-wrapper').length) {
                questionRefDetailSite = jQuery(ev.target).parents('.dialogue-summary').find(".quacks-question-hyperlink").attr('href');
                questionTextDetailSite = jQuery(ev.target).parents('.dialogue-summary').find(".quacks-question-hyperlink").text();                
            } else {                
                var canonicalElement = document.querySelector('link[rel=canonical]');
                if (canonicalElement !== null) {
                    questionRefDetailSite = canonicalElement.href;
                } else {
                    questionRefDetailSite = window.location.href;
                }
                questionTextDetailSite = jQuery('.quacks-headline').find(".quacks-question-hyperlink").text();
            }            

            createShareCard(questionRefDetailSite, questionTextDetailSite);
        });

        function createShareCard(href, question) {   

            if (navigator.share) {
                navigator.share({
                    title: question,
                    text: 'Post',
                    url: href
                }).then(function () {
                    return console.log('Successful sharing');
                }).catch(function (error) {
                    return console.log('Error sharing', error);
                });
            } else if (!navigator.share) {
                jQuery(".quacks-share-url").val(href);
                jQuery('#quacks-share-modal').css({ "display": "flex" });
                styleShareShariff(href, question);
                copytoClipboard(href);
                closeShareCard();
            }
        }
    }  

    copytoClipboard(inputVal) {
        jQuery(document).on('click', '.content-copy', function () {
            this.copied = false;

            // Create textarea element
            let textarea = document.createElement('textarea');

            // Set the value of the text
            textarea.value = inputVal;

            // Make sure we cant change the text of the textarea
            textarea.setAttribute('readonly', '');

            // Hide the textarea off the screnn
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';

            // Add the textarea to the page
            document.body.appendChild(textarea);

            // Copy the value of the textarea
            textarea.select();

            try {
                var successful = document.execCommand('copy'); //jshint ignore:line
                this.copied = true;
            } catch (err) {
                this.copied = false;
            }

            textarea.remove();
        });
    }

    closeShareCard() {
        jQuery(document).on('click', '#quacks-close-share-modal', function () {
            jQuery('#quacks-share-modal').css({ "display": "none" });
        });
    }

    /* global Materialize */
    /* exported styleShariff */

    initMaterializeInContentBlock() {

        let elems = document.querySelectorAll('.quack-inner-block');
        M.ScrollSpy.init(elems, { scrollOffset: 75});
    
        elems = document.querySelectorAll('.materialboxed');
        M.Materialbox.init(elems);
    
        elems = document.querySelectorAll('.collapsible');
        M.Collapsible.init(elems);
    
        elems = document.querySelectorAll('.collapsible.expandable');
        M.Collapsible.init(elems, {accordion: false});
    }

    initialise () {
        const instance = this;
        instance.initShareContent();
        instance.initMaterializeInContentBlock();
    }
}