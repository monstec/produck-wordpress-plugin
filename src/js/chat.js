/* global produckLib */
/* global M */
/* global Cookies */

export default class InitChat {
  constructor() {
  }

  initChatJs() {
    
    setTimeout(function () {
      jQuery("#produck-chat-block-home")
        .not("quacks-active")
        .find(".produck-chat-link")
        .addClass("quacks-pulse");
      setTimeout(function () {
        jQuery("#produck-chat-block-home")
          .not("quacks-active")
          .find(".produck-chat-link")
          .removeClass("quacks-pulse");
      }, 10000);
    }, 20000);

    function initProduckPopupSettings() {
      // if popup open = active, a click refers to produck.de
      jQuery(document).on("click", "#produck-chat-block-home", function () {
        jQuery(this).toggleClass("quacks-active");
      });
      // closes the popup on click outside of it
      jQuery(document).on("click", function (e) {
        var produckPopup = jQuery("#produck-chat-block-home.quacks-active");

        if (
          !produckPopup.is(e.target) &&
          produckPopup.has(e.target).length === 0
        ) {
          produckPopup.removeClass("quacks-active");
        }
      });
    }

    initProduckPopupSettings();

    let port1 = null;
    let port2 = null;

    // Sets up a new MessageChannel
    // so we can return a Promise
    function sendCookieData() {
      return new Promise((resolve) => {
        const channel = new MessageChannel();
        port1 = channel.port1;
        port2 = channel.port2;

        // this will fire when iframe will answer
        port1.onmessage = (e) => {
          handleMessageFromIframe(e);
          resolve(e.data);
        };

        // let iframe know we're ready tp get an answer
        // send it its own port
        const iframe = document.getElementById("produck-iframe");
        iframe.contentWindow.postMessage("HereIsYourPort", "*", [port2]);
      });
    }

    function initIframeCommunication() {
      const allowedOrigins = [
        'https://produck.de',
        'https://www.produck.de',
        // @if ENV!='production'
        'https://localhost'
        // @endif
    ];

      window.onmessage = (e) => {
        if (
          e.data === "sendPortToProduck" &&
          allowedOrigins.includes(e.origin)
        ) {
          sendCookieData();
        }
      };
    }

    initIframeCommunication();

    function handleMessageFromIframe(e) {
      const payload = JSON.parse(e.data);

      switch (payload.method) {
        case "set":
          Cookies.set(payload.key, JSON.stringify(payload.data), {
            expires: payload.expiration,
          });
          break;
        case "get":
          const data = Cookies.get(payload.key);
          const returnPayload = {
            method: "storage#get",
            cookieData: data,
            exchangeId: payload.exchangeId,
          };
          port1.postMessage(JSON.stringify(returnPayload));
          break;
        case "remove":
          Cookies.remove(payload.key);
          break;
        case "clear":
          Cookies.remove("sess_au");
          Cookies.remove("sess_re");
          Cookies.remove("produck");
          Cookies.remove("chat");
          break;
      }

      return "iframe request accomplished";
    }
  }
}
