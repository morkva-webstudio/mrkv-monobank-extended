const settings_mrkv_mono = window.wc.wcSettings.getSetting( 'morkva-monopay_data', {} );
const label_mrkv_mono = window.wp.htmlEntities.decodeEntities( settings_mrkv_mono.title );

const htmlToElem_mrkv_mono = ( html ) => wp.element.RawHTML( { children: html } );

const Mrkv_Mono_Gateway = {
    name: 'morkva-monopay',
    label: window.wp.element.createElement(() =>
      window.wp.element.createElement(
        "span",
        null,
        window.wp.element.createElement("img", {
          src: settings_mrkv_mono.icon,
          alt: label_mrkv_mono,
        }),
        "  " + label_mrkv_mono
      )
    ),
    content: htmlToElem_mrkv_mono(settings_mrkv_mono.description),
    edit: htmlToElem_mrkv_mono(settings_mrkv_mono.description),
    canMakePayment: () => true,
    ariaLabel: label_mrkv_mono,
    supports: {
        features: settings_mrkv_mono.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Mrkv_Mono_Gateway );