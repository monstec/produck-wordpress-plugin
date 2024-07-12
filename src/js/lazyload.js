/* global produckLib */
/* global M */

export default class LazyLoad {
    constructor(){
    }
    
    lazyloaderInit() {

        if ('loading' in HTMLIFrameElement.prototype) {

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    let element = entry.target;

                    if (entry.intersectionRatio > 0.0 && element.loading !== "eager") {
                        element.loading = "eager";
                    }

                    observer.unobserve(element);
                });
            },
              {}
            );

            for (let images of document.querySelectorAll('img[loading="lazy"]')) {
                observer.observe(images);
            }

            for (let iframes of document.querySelectorAll('iframe[loading="lazy"]')) {
                observer.observe(iframes);
            }

        } else {
            const images = document.querySelectorAll('img[loading="lazy"]');

            images.forEach(img => {
                img.dataset.src = img.src;
            });

            const iframes = document.querySelectorAll('iframe[loading="lazy"]');

            iframes.forEach(iframe => {
                iframe.dataset.src = iframe.src;
            });

            // Dynamically import the LazySizes library
            const script = document.createElement('script');
            script.src =
              'https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js';
            document.body.appendChild(script);
        }
    }
}
