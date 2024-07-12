/* global produck */
/* global M */

/* Copyright (c) MonsTec GmbH 2024 | https://monstec.de
 ** Author: Dr. Joerg Heinze
 */

import i18next from "i18next";

export default class LinkifyText {
  constructor() {
    this.asinRunIdCache = [];
    this.requestedAsinsCache = [];
    this.adsCache;
  }

  initialise() {
    let instance = this;
    let teaserContent = jQuery(".entry-headline"),
      mainContent = jQuery(".entry-content"),
      userId =
        jQuery("#author-details-block").data("author-id") != null
          ? jQuery("#author-details-block").data("author-id")
          : 4079;

    if (teaserContent != undefined && teaserContent.length > 0)
      instance.linkifyDialogue(teaserContent, userId);
    if (mainContent != undefined && mainContent.length > 0)
      instance.linkifyDialogue(mainContent, userId);
  }

  buildWidgetItem(productObj, widgetType) {
    let instance = this;

    let teaserList = "",
      primeString = "",
      textToViewportRatio = window.matchMedia("(max-width: 991px)").matches
        ? 55
        : 150;

    if (productObj.features !== null) {
      let teaserListElems = "";

      productObj.features.forEach((bulletPoint, i) => {
        let bulletPointHtmlFree = bulletPoint.replace(/<[^>]*>+/gm, "").trim();

        if (i <= 2)
          teaserListElems +=
            bulletPointHtmlFree.length > textToViewportRatio
              ? "<li>" +
                bulletPointHtmlFree.substr(0, textToViewportRatio) +
                "...</li>"
              : "<li>" + bulletPointHtmlFree + "</li>";
      });

      teaserList = "<ul>" + teaserListElems + "</ul>";
    } else if (productObj.features !== null && productObj.description) {
      teaserList =
        "<ul><li>" +
        productObj.description.substr(0, textToViewportRatio) +
        "</li></ul>";
    }

    function transformPrice(price, currency, alternativeText) {
      function setAlternativeTextAndTriggerAnalytics() {
        console.log("Product Price Not Found");
        return alternativeText;
      }

      return price && price !== null
        ? price.toFixed(2) + "&nbsp;" + currency
        : setAlternativeTextAndTriggerAnalytics();
    }

    if (productObj.premiumDelivery)
      primeString =
        '<a class="prime-status" href="https://www.amazon.de/gp/prime/?tag=monstec-21" title="Amazon Prime" rel="nofollow" target="_blank"><img src="https://produck.de/assets/img/icons/amazon-prime.png" alt="Amazon Prime Logo" loading="lazy"></a>';

    let price = transformPrice(
        productObj.price,
        productObj.currency,
        "Ohne Preisangabe"
      ),
      basePrice =
        productObj.basePrice > productObj.price
          ? transformPrice(productObj.basePrice, productObj.currency, "", false)
          : "",
      discount =
        basePrice.length > 0 && productObj.discount !== null
          ? "-" + productObj.discount
          : "",
      teaserString = '<div class="product-teaser">' + teaserList + "</div>",
      metaInfos =
        '<div class="product-meta-info"><span class="product-baseprice meta-info-item">' +
        basePrice +
        '</span><span class="product-discount meta-info-item">' +
        discount +
        '</span><span class="product-price meta-info-item">' +
        price +
        "</span>" +
        primeString +
        "</div>",
      getMinutes =
        productObj.lastUpdate.minute < 10
          ? "0" + productObj.lastUpdate.minute
          : productObj.lastUpdate.minute,
      widgetString =
        '<div class="prdk-widget">' +
        '<div class="product-block" data-product-id="' +
        productObj.referenceId +
        '" data-product-title="' +
        productObj.productName +
        '">' +
        '<div class="product-block-inner ' +
        widgetType +
        '-widget">' +
        '<a class="product-image-link" href="' +
        productObj.productUrl +
        '" title="Link zu ' +
        productObj.productName +
        '" rel="nofollow sponsored noopener" target="_blank"><img src="' +
        productObj.imageUrl +
        '" title="Bild von ' +
        productObj.productName +
        '" el="nofollow" target="_blank" alt="' +
        productObj.productName +
        '" loading="lazy" /></a>' +
        '<div class="product-content">' +
        '<a class="product-title prdk-link" href="' +
        productObj.productUrl +
        '" title="' +
        productObj.productName +
        '" rel="nofollow sponsored noopener" target="_blank">' +
        (productObj.productName.length > textToViewportRatio
          ? productObj.productName.substr(0, textToViewportRatio) + "..."
          : productObj.productName) +
        "</a>" +
        teaserString +
        metaInfos +
        '<div class="product-button">' +
        '<a class="prdk-btn amazon-buy-btn" href="' +
        productObj.productUrl +
        '" title="' +
        i18next.t("text.view_on_amzn") +
        '" target="_blank" rel="nofollow noopener"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M257.2 162.7c-48.7 1.8-169.5 15.5-169.5 117.5 0 109.5 138.3 114 183.5 43.2 6.5 10.2 35.4 37.5 45.3 46.8l56.8-56S341 288.9 341 261.4V114.3C341 89 316.5 32 228.7 32 140.7 32 94 87 94 136.3l73.5 6.8c16.3-49.5 54.2-49.5 54.2-49.5 40.7-.1 35.5 29.8 35.5 69.1zm0 86.8c0 80-84.2 68-84.2 17.2 0-47.2 50.5-56.7 84.2-57.8v40.6zm136 163.5c-7.7 10-70 67-174.5 67S34.2 408.5 9.7 379c-6.8-7.7 1-11.3 5.5-8.3C88.5 415.2 203 488.5 387.7 401c7.5-3.7 13.3 2 5.5 12zm39.8 2.2c-6.5 15.8-16 26.8-21.2 31-5.5 4.5-9.5 2.7-6.5-3.8s19.3-46.5 12.7-55c-6.5-8.3-37-4.3-48-3.2-10.8 1-13 2-14-.3-2.3-5.7 21.7-15.5 37.5-17.5 15.7-1.8 41-.8 46 5.7 3.7 5.1 0 27.1-6.5 43.1z"/></svg>' +
        i18next.t("text.view_on_amzn") +
        "</a>" +
        "</div>" +
        '<div class="product-notes">' +
        '<span class="product-price-info">' +
        i18next.t("text.price_incl_vat") +
        productObj.lastUpdate.dayOfMonth +
        "." +
        productObj.lastUpdate.monthValue +
        "." +
        productObj.lastUpdate.year +
        " " +
        productObj.lastUpdate.hour +
        ":" +
        getMinutes +
        ' (UTC). <a href="#affiliate-note">' +
        i18next.t("text.more_info") +
        "</a></span>" +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>";

    return widgetString;
  }

  linkifyText() {
    let instance = this;

    const uglyLinksPattern =
      /\[url(?:=|&#61;)("|&quot;|&#34;)(.*?)\1(?:,name(?:=|&#61;)("|&quot;|&#34;))(.*?)\1(?:,title(?:=|&#61;)("|&quot;|&#34;))(.*?)\1]/gim;

    const asinPattern =
      /\[asin(?:=|&#61;)("|“|&quot;|&#34;)(.*?)\1(?:,type(?:=|&#61;)("|“|&quot;|&#34;))(.*?)\1]/gim;

    const tableOfContentPattern = /\[(tableofcontent|toc)]/i;

    const inTextDealsPattern = /\[(topdeals)]/gi;

    // http://, https://, ftp://
    const urlPattern =
      /\b(?![^<|\[]*[>\]])(?:https?|ftp):\/\/([a-z0-9-+&@#\/%?=~_|!:,.;]*[a-z0-9-+&@#\/%=~_|])/gim;
    // www. sans http:// or https://
    const pseudoUrlPattern = /(^|[^\/])(\bwww\.[a-z0-9-+&@#\/%?=~_|!:,.;]*[a-z0-9-+&@#\/%=~_|])/gim;
    // Email addresses
    const emailAddressPattern =
      /(?![^<|\[]*[>\]])[\w.]+@[a-zA-Z_-]+?(?:\.[a-zA-Z]{2,6})+/gim;

    if (!String.linkify) {
      String.prototype.linkify = function (note, userId) {
        let textInput = this;

        let asinTagMatches = [...textInput.matchAll(asinPattern)];

        let linkifyRunUid = Math.random().toString(16).slice(2);

        let verifyInternalOrigin = function (
          matchVal,
          returnValFalse,
          returnValTrue
        ) {
          return /\.siio.de|:\/\/siio\.de|produck\.de/.test(matchVal)
            ? returnValTrue
              ? returnValTrue
              : ""
            : returnValFalse
            ? returnValFalse
            : "";
        };

        //replace arguments defined here: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/replace
        let beautifyLinks = function (
          match,
          p1,
          p2,
          p3,
          p4,
          p5,
          p6,
          offset,
          string
        ) {
          let linkNote = verifyInternalOrigin(p2, note);
          let relAttr = verifyInternalOrigin(
            p2,
            'target="_blank" rel="noopener nofollow ugc"',
            'rel="ugc"'
          );
          let newLink =
            "<a " +
            relAttr +
            ' href="' +
            p2 +
            '" title="' +
            p6 +
            '">' +
            p4 +
            "</a>" +
            linkNote;
          return newLink;
        };

        if (asinTagMatches.length > 0) {
          let asinsDuplRed = [
            ...new Set(asinTagMatches.map((pattern) => pattern[2])),
          ];

          //check if ASINS were already requested and if so remove those from api request
          if (instance.requestedAsinsCache.length) {
            asinsDuplRed.forEach(function (el) {
              if (instance.requestedAsinsCache[0].includes(el)) {
                asinsDuplRed = asinsDuplRed.filter((x) => x !== el);

                console.log(
                  "RemainingRequestAsinsAfterIteration: ",
                  asinsDuplRed
                );
              } else {
                instance.requestedAsinsCache[0].push(el);
              }
            });
          } else {
            instance.requestedAsinsCache.push(asinsDuplRed);
          }

          console.log("remainingUniqueAsins: ", asinsDuplRed);

          // put placeholders for the widgets in the input that will be replaced later (equal asins get same runid across blocks)
          // as soon as the widget data will have been fetched from the catalogue service
          let insertWidgetPlaceholder = function (
            match,
            p1,
            asin,
            p3,
            widgetType
          ) {
            //let placeHolderReference = linkifyRunUid + "-" + asin;
            let placeHolderReference;
            let loaderPart = instance.getLoaderHtml("small");
            let runIdFromCache = instance.asinRunIdCache[asin]; //if asin was not requested before with other runId

            if (runIdFromCache) {
              placeHolderReference = runIdFromCache + "-" + asin;
              linkifyRunUid = runIdFromCache;
            } else {
              placeHolderReference = linkifyRunUid + "-" + asin;
              instance.asinRunIdCache[asin] = linkifyRunUid;
            }

            let replacement =
              `<div class="js-widget-placeholder full-width-center-content-row" data-widget-type="${widgetType}" ` +
              `data-ref="${placeHolderReference}" style="margin: 20px 0">${loaderPart}</div>`;

            return replacement;
          };

          textInput = textInput.replace(asinPattern, insertWidgetPlaceholder);

          if (asinsDuplRed.length > 0) {
            let amazonProductsApiUri =
              "https://api.produck.de/catalogue/thirdpartyoffers/asins?userId=" +
              userId;

            asinsDuplRed.forEach(
              (asin) => (amazonProductsApiUri += "&ids=" + asin)
            );

            jQuery.ajax({
              type: "GET",
              url: amazonProductsApiUri,
              success: function (productDataRecords) {
                return instance._replacePlaceholdersWithWidgets(
                  productDataRecords,
                  instance.asinRunIdCache[asinsDuplRed[0]]
                );
              },
              error: function (err) {
                // error handler
                console.log(
                  "Could not retrieve product information. Amazonify failed. Status: " +
                    err.status
                );
              },
            });
          }
        }

        let attrExtLink = 'target="_blank" rel="noopener nofollow ugc"';
        let attrIntLink = 'rel="ugc"';

        //caution - order of replace method matters
        return textInput
          .replace(uglyLinksPattern, beautifyLinks)
          .replace(
            urlPattern,
            (match) =>
              `<a ${verifyInternalOrigin(
                match,
                attrExtLink,
                attrIntLink
              )} href="${match}">${match}</a>${verifyInternalOrigin(
                match,
                note
              )}`
          )
          .replace(
            pseudoUrlPattern,
            (match, p1, p2) =>
              `${p1}<a ${verifyInternalOrigin(
                p2,
                attrExtLink,
                attrIntLink
              )} href="https://${p2}">${p2}</a>${verifyInternalOrigin(
                p2,
                note
              )}`
          )
          .replace(emailAddressPattern, '<a href="mailto:$&">$&</a>');
      };
    }

    if (!String.addTableOfContent) {
      String.prototype.addTableOfContent = function (target, setAttrId) {
        let textInput = this;

        function buildToC() {
          let tocListElems = "";

          //create index list linked to headlines
          target.each(function (index, obj) {
            let newObj = jQuery(obj)[0];
            let text = jQuery(newObj).text();

            function setID() {
              let newId = text.split(" ")[0] + "-" + index;
              setAttrId(newId.toLowerCase(), index);
              return newId.toLowerCase();
            }

            let id =
              typeof jQuery(newObj).attr("id") !== "undefined" &&
              jQuery(newObj).attr("id") !== false
                ? jQuery(newObj).attr("id")
                : setID();

            tocListElems += `<li><a href="#${id}">${text}</a></li>`;
          });

          let toc =
            '<div class="prdk-toc"><h2 id="toc-headline">'+i18next.t('text.in_this_article')+'</h2><ol class="table-of-contents">' +
            tocListElems +
            "</ol></div>";

          return toc;
        }

        return textInput.replace(tableOfContentPattern, buildToC);
      };
    }

    if (!String.addDealBox) {
      String.prototype.addDealBox = function (matchedProdArr) {
        let textInput = this;

        function buildInTextOffer() {
          let offerListElems = "";
          let today = new Date().getDay();
          let time = today > 10 ? "08:15" : today > 20 ? "08:17" : "08:11";

          for (const [index, item] of matchedProdArr.entries()) {
            let price = item.price ? " für " + item.price + "&euro;" : "";
            offerListElems += `<li><a class="fs-14" href="${item.link}" target="_blank">${item.title}${price}*</a></li>`;
            if (index === 2) break;
          }

          let text =
            '<div class="prdk-intxt-box"><strong>'+i18next.t('text.these_offers_might_interest_you')+'</strong><ul>' +
            offerListElems +
            '</ul><span id="marketing-cookie-hint">Stand: ' +
            time +
            ' (UTC). <a href="#affiliate-note">' +
            i18next.t("text.more_info") +
            '</a> | '+i18next.t("text.support_us")+'</span></div>';

          return text;
        }

        let replaceString = matchedProdArr.length > 0 ? buildInTextOffer() : "";
        return textInput.replace(inTextDealsPattern, replaceString);
      };
    }

    return {
      uglyLinksPattern,
      urlPattern,
      pseudoUrlPattern,
      emailAddressPattern,
      asinPatterns: asinPattern,
      tableOfContentPattern,
      inTextDealsPattern,
    };
  }

  /**
   * Replaces placeholders on the page that are designated for advertisement widgets.
   *
   * @param {*} advertisementItems an array of advertisement items
   */
  _replacePlaceholdersWithWidgets(advertisementItems, placeholderRefPrefix) {
    const instance = this;
    if (!advertisementItems) return;

    function replaceFnc() {
      advertisementItems.forEach(function (advertisementItem) {
        let placeHoldersForAsin = jQuery(
          `div[data-ref=${placeholderRefPrefix}-${advertisementItem.referenceId}]`
        );

        placeHoldersForAsin.each(function (index, placeholder) {
          let placeholderElement = jQuery(placeholder);
          let widgetType = placeholderElement.attr("data-widget-type");
          let widgetHtml = instance.buildWidgetItem(
            advertisementItem,
            widgetType
          );

          placeholderElement.replaceWith(jQuery(widgetHtml));
        });
      });
    }

    replaceFnc();

    function replaceRemainingPlaceholders() {
      let remainingElements = jQuery(
        ".js-widget-placeholder[data-ref^='" + placeholderRefPrefix + "-']"
      );

      if (remainingElements.length) {
        console.log("PRODUCT LOAD RETRY");

        replaceFnc();

        let remainingElementsAfterRetry = jQuery(
          ".js-widget-placeholder[data-ref^='" + placeholderRefPrefix + "-']"
        );

        if (remainingElementsAfterRetry.length) {
          remainingElements.replaceWith(
            '<p class="fs-12" style="text-indent: 20px"><em>'+i18next.t('text.produc_not_found')+'</em><p>'
          );

          console.log("PRODUCT LOAD FAILED");
        } else {
          console.log("PRODUCT LOAD RETRY SUCCEEDED");
        }
      }
    }

    setTimeout(() => {
      replaceRemainingPlaceholders();
      instance.clearAsinCache();
    }, 10000);
  }

  clearAsinCache() {
    let instance = this;
    instance.asinRunIdCache = [];
    instance.requestedAsinsCache = [];
  }

  // convert url in textelems to clickable links
  async linkifyDialogue(textElem, userId) {
    let instance = this;
    let linkPatterns = instance.linkifyText(),
      textinHTML = textElem.html(),
      linkFound = false,
      missingIdArr = [];

    function setAttrId(name, i) {
      missingIdArr.push({ id: name, index: i });
    }

    // just replace text if containing urlPattern
    if (
      textinHTML.match(linkPatterns.uglyLinksPattern) ||
      textinHTML.match(linkPatterns.urlPattern) ||
      textinHTML.match(linkPatterns.pseudoUrlPattern) ||
      textinHTML.match(linkPatterns.emailAddressPattern) ||
      textinHTML.match(linkPatterns.asinPatterns)
    ) {
      let note = "*",
        linkedText = await textinHTML.linkify(note, userId),
        headlinesElems;

      if (textinHTML.match(linkPatterns.tableOfContentPattern)) {
        headlinesElems = textElem.find('h2[id^="headline-"]');
        linkedText = linkedText.addTableOfContent(headlinesElems, setAttrId);
      }

      if (textinHTML.match(linkPatterns.inTextDealsPattern)) {
        let tagElems = Array.from(
          document.head.querySelectorAll(
            '[property~="og:article:tag"][content]'
          )
        );

        let tagsArr = tagElems.map((el) => el.content);

        let matchedProductsArr = instance._getContextRelatedAds(tagsArr);
        if (matchedProductsArr) {
          matchedProductsArr = matchedProductsArr.filter(
            (element) =>
              element.matches > 0 &&
              instance.checkExpirationDate(element.expirationDate)
          );
          linkedText = linkedText.addDealBox(matchedProductsArr);
        } else {
          linkedText = linkedText.addDealBox(false);
        }
      }

      textElem.html(linkedText);
      linkFound = true;

      missingIdArr.forEach((el) => {
        headlinesElems.eq(el.index).attr("id", el.id);
      });

      if (jQuery("#affiliate-note").length === 0) setAffiliateNote();
    }

    function setAffiliateNote() {
      if (linkFound && jQuery("#affiliate-note").length === 0) {
        let affiliateNote =
          '<hr><p id="affiliate-note">'+i18next.t('text.affiliate_note')+'</p>';
        textElem.append(affiliateNote);
      }
    }
  }

  transformDate(timestamp) {
    var dd = timestamp.getDate();
    var mm = timestamp.getMonth() + 1; //January is 0!
    var yyyy = timestamp.getFullYear();
    if (dd < 10) {
      dd = "0" + dd;
    }
    if (mm < 10) {
      mm = "0" + mm;
    }
    var date = dd + "." + mm + "." + yyyy;
    return date;
  }

  getLoaderHtml(size) {
    let sizeClass = "";
    if (
      size === "big" ||
      size === "medium" ||
      size === "small" ||
      size === "tiny" ||
      size === "adaptive"
    ) {
      sizeClass = " " + size;
    }

    return `<div class="js-produck-loader loader${sizeClass}"></div>`;
  }

  checkExpirationDate(expirationDate) {
    let transformedExpDate = new Date(expirationDate);
    let currentDate = new Date();
    let entityValid = false;

    if (transformedExpDate.getTime() >= currentDate.getTime())
      entityValid = true;

    return entityValid;
  }

  _getContextRelatedAds(tagsArray) {
    let instance = this;
    let adsItem = Object.entries(instance.adsObj[0].adsItems);

    if (instance.adsCache) {
      return instance.adsCache;
    } else {
      let adsItemRed = adsItem.map((element) => element[1]);

      for (let el of adsItemRed) {
        el.matches = 0;

        for (let tagString of tagsArray) {
          if (el.tag.toLowerCase().includes(tagString.toLowerCase()))
            el.matches++;
        }
      }

      let sortedArray = adsItemRed.sort((a, b) => b.matches - a.matches);

      instance.adsCache = sortedArray;

      return sortedArray;
    }
  }
}
