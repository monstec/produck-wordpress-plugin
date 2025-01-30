import i18next from "i18next";
import jqueryI18next from "jquery-i18next";
import LanguageDetector from "i18next-browser-languagedetector";

// //const getCurrentLng = () => i18next.language || window.localStorage.i18nextLng || '';
// //console.log(i18next.language, window.localStorage.i18nextLng, getCurrentLng);

i18next.use(LanguageDetector).init(
  {
    detection: {
      // order and from where user language should be detected
      order: [
        "querystring",
        "cookie",
        "localStorage",
        "navigator",
        "htmlTag",
        "path",
        "subdomain",
      ],

      // keys or params to lookup language from
      lookupQuerystring: "lng",
      lookupCookie: "i18next",
      lookupLocalStorage: "i18nextLng",
      lookupFromPathIndex: 0,
      lookupFromSubdomainIndex: 0,

      // cache user language on
      caches: ["localStorage", "cookie"],
      excludeCacheFor: ["cimode"], // languages to not persist (cookie, localStorage)

      // optional expire and domain for set cookie
      cookieMinutes: 10000,

      // optional htmlTag with lang attribute, the default is:
      htmlTag: document.documentElement,
    },
    fallbackLng: ["en"],
    debug: false,
    returnObjects: true,
    resources: {
      de: {
        translation: {
          locales: {
            de: "Deutsch",
            en: "Englisch",
          },
          general: {
            ok: "Ok",
            language_label: "Sprache",
          },
          navigation: {
            article: "Artikel",
          },
          quackpage: {
            profile_ref: "Zum Profil",
            speciality: "Spezialgebiet",
          },
          settings: {
            settings: "Einstellungen",
            settings_title: "Einstellungen",
          },
          text: {
            affiliate_note: "* Bitte beachten Sie, dass Links auf dieser Seite Links zu Werbepartnern sein können. Für Käufe, die über einen dieser Links zustande kommen, erhalten die Autoren (falls sie die Marketingcookies des Werbepartners annehmen) Provision. Ihnen entstehen dadurch keine zusätzlichen Kosten. Sie unterstützen lediglich den Autor. Preise, Lieferbedingungen und Verfügbarkeiten entsprechen dem angegebenen Stand (Datum/Uhrzeit) und können sich jederzeit ändern. Angaben auf dieser Seite weichen daher ggf. von denen der Partnerseiten ab. Für den Kauf eines betreffenden Produkts gelten die Angaben zu Preis und Verfügbarkeit, die zum Kaufzeitpunkt auf der/den maßgeblichen Website(s) (z.B. Amazon) angezeigt werden. Bestimmte Inhalte, die auf dieser Website angezeigt werden, stammen von Amazon. Diese Inhalte werden‚ 'wie besehen' bereitgestellt und können jederzeit geändert oder entfernt werden.",
            all_settings: "Alle Einstellungen",
            answered_on: "beantwortet am",
            article_overview: "Artikelübersicht",
            buy_on_amzn: "Auf Amazon kaufen*",
            chat_with: "Chat mit ",
            close: "Schließen",
            copy: "Kopieren",
            current_posts: "Aktuelle Beiträge",
            current_posts_by_external_authors: "Aktuelle Beiträge von externen Autoren",
            date_on: "vom ",
            external_article: "ProDuck Gastartikel",
            external_chat: "ProDuck Chat Transkript",
            external_posts: "ProDuck Gastbeiträge",
            expert: "Experte",
            find_exciting_articles_chats_questions: "Finde spannende Artikel, Chats und Fragen",
            go_to_post_overview: "Zur Beitragsübersicht",
            in_this_article: "In diesem Artikel",
            last_updated_on: "Zuletzt aktualisiert am ",
            more_info: "Mehr Informationen*",
            more_posts: "Mehr Beiträge",
            notify: "Melden",
            price_incl_vat: "Preis inkl. MwSt., zzgl. Versandkosten. Letzte Aktualisierung am ",
            post_overview: "Beitragsübersicht",
            product_not_found: "Produkt nicht gefunden",
            published_on: "Veröffentlicht am",
            rating: "Bewertungen",
            share: "Teilen",
            share_link: "Link teilen",
            share_page: "Seite teilen",
            support_us: "Sie möchten uns unterstützen? Für Käufe über unsere Partnershops erhalten wir eine Provision. Für ein korrektes Tracking ist es jedoch notwendig, dass Sie die Marketingcookies unserer Partner annehmen. Der Preis ändert sich dadurch nicht. Wir würden uns über Ihre Unterstützung freuen und wünschen weiterhin viel Spaß auf unserer Seite.",
            these_offers_might_interest_you: "Folgende Angebote könnten dich interessieren",
            view_on_amzn: "Auf Amazon ansehen*",
            views: "Ansichten",
            written_by: "von ",
          },
          shariff: {
            mailBody1:
              "Hi, gerne m%c3%b6chte ich dir den Service von {url} empfehlen. Dort kann man sich zu verschiedensten Produkten beraten lassen und Produkte im Chat erwerben. Probier's bei Gelegenheit mal aus!",
            mailBody2: "Hi, über folgenden Link bin ich erreichbar",
            shareQuack: "Meine Empfehlung",
            title: "ProDuck - The expert among the online portals",
          },
        },
      },
      en: {
        translation: {
          locales: {
            de: "German",
            en: "English",
          },
          general: {
            ok: "Ok",
            language_label: "Language",
          },
          navigation: {
            article: "Article",
          },
          quackpage: {
            profile_ref: "Go to profile",
            speciality: "Speciality",
          },
          settings: {
            settings: "Settings",
            settings_title: "Settings",
          },
          text: {
            affiliate_note: "* Please note that links on this page may be affiliate links. For purchases made through any of these links, the author receives a commission (if you accept the partner's marketing cookies). There are no additional costs for you. However, you support the author. Prices, delivery conditions and availabilities correspond to the specified status (date/time) and can change at any time. Information on this page may therefore differ from those on the partner sites. For the purchase of a specific product, the price and availability information displayed on the relevant website(s) (e.g., Amazon) at the time of purchase applies. Certain content displayed on this page comes from Amazon. This content is provided 'as is' and may be changed or removed at any time.",
            all_settings: "All Settings",
            answered_on: "Answered on",
            article_overview: "Article Overview",            
            buy_on_amzn: "Buy on Amazon*",
            chat_with: "Chat with ",
            close: "Close",
            copy: "Copy",
            current_posts: "Current Posts",
            current_posts_by_external_authors: "Current Posts by External Authors",
            date_on: "from ",
            external_article: "ProDuck Guest Article",
            external_chat: "ProDuck Chat Transcript",
            external_posts: "ProDuck Guest Posts",
            expert: "Expert",
            find_exciting_articles_chats_questions: "Find exciting articles, chats, and questions",
            go_to_post_overview: "To Post Overview",
            in_this_article: "In this article",
            last_updated_on: "Last updated on ",
            more_info: "More Information*",
            more_posts: "More Posts",
            notify: "Notify",
            price_incl_vat: "Price incl. VAT, excl. shipping costs. Last update on ",
            post_overview: "Post Overview",
            product_not_found: "Product not found",
            published_on: "Published on",
            rating: "Rating",
            share: "Share",
            share_link: "Share Link",
            share_page: "Share Page",
            support_us: "Do you want to support us? For purchases made through our partner shops, we receive a commission. For correct tracking, it is necessary for you to accept our partners' marketing cookies. The price does not change because of this. We would appreciate your support and wish you continued enjoyment on our site.",
            these_offers_might_interest_you: "These offers might interest you",
            view_on_amzn: "View on Amazon*",
            views: "Views",
            written_by: "by ",
          },
          shariff: {
            mailBody1:
              "Hi, I want to recommend the following service to you {url}. There you can get advise and support to a various number of products. Check it out!",
            mailBody2: "Hi, you can reach me via the following link",
            shareQuack: "My recommendation",
            title: "ProDuck - The expert among the online portals",
          },
        },
      },
    },
  },
  function (err, t) {
    if (err) {
      console.error(err);
      return;
    }

    // Initialize jquery-i18next
    jqueryI18next.init(i18next, jQuery, {
      tName: "t", // --> appends $.t = i18next.t
      i18nName: "i18n", // --> appends $.i18n = i18next
      handleName: "localize", // --> appends $(selector).localize(opts);
      selectorAttr: "data-i18n", // selector for translating elements
      targetAttr: "i18n-target", // data-() attribute to grab target element to translate (if different than itself)
      optionsAttr: "i18n-options", // data-() attribute that contains options, will load/set if useOptionsAttr = true
      useOptionsAttr: false, // see optionsAttr
      parseDefaultValueFromContent: true, // parses default values from content ele.val or ele.text
    });

    jQuery('.main').localize();
  }
);

export default i18next;