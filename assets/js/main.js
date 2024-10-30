;(($) => {
    $(document).ready(() => {

        const helpers = window.cpHelpers || window.cplHelpers;

        let cryptoPayStartedApp;
        const cryptoPayProcess = (data) => {
            CryptoPayApp.modal.open();
            if (!cryptoPayStartedApp) {
                cryptoPayStartedApp = CryptoPayApp.start(data.item, {
                    metadata: data.metadata
                });
            } else {
                cryptoPayStartedApp.reStart(data.item, {
                    metadata: data.metadata,
                });
            }
        }

        let cryptoPayLiteStartedApp;
        const cryptoPayLiteProcess = (data) => {
            CryptoPayLiteApp.modal.open();
            if (!cryptoPayLiteStartedApp) {
                cryptoPayLiteStartedApp = CryptoPayLiteApp.start(data.item, {
                    metadata: data.metadata
                });
            } else {
                cryptoPayLiteStartedApp.reStart(data.item, {
                    metadata: data.metadata,
                });
            }
        }

        $(document).on('click', '.ldlms-cp-btn', (e) => {
            const name = $(e.target).data('name');
            const stringData = $(e.target).data('json');
            const data = JSON.parse(stringData.replaceAll('\'', ''));

            $.ajax({
                url: LDLMSCP.ajaxUrl,
                type: 'POST',
                data: {
                    action: LDLMSCP.action,
                    productId: data.productId,
                },
                beforeSend: () => {
                    helpers.waitingPopup(LDLMSCP.lang.waiting);
                },
                success: (response) => {
                    helpers.closePopup();
                    if (response.success) {
                        if (name === 'cryptopay') {
                            cryptoPayProcess(response.data);
                        } else if (name === 'cryptopay_lite') {
                            cryptoPayLiteProcess(response.data);
                        }
                    } else {
                        helpers.errorPopup(response.data.msg);
                    }
                },
                error: (error) => {
                    if (error?.response?.data?.msg) {
                        helpers.errorPopup(error.response.data.msg);
                    } else {
                        alert(error.responseText);
                    }
                }
            });
        })
    });
})(jQuery);