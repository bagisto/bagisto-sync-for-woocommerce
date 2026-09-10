/**
* Collapsible item click handler.
* @param {HTMLElement} el Collapsible item element.
*/
export const collapseItem = (el) => {
    let isAdd = el.classList.toggle('wkwctobag-collapsible-item-open');
    const content = el.closest('.wkwctobag-bagisto-wrapper').querySelector('.wkwctobag-bagisto-content');

    if (!!content) {
        content.style.height = !isAdd ? 'fit-content' : 0 + 'px';
    }
}

/**
 * Fetch request helper function.
 * @param {String} url Request URL.
 * @param {String} method Request method.
 * @param {Object} body Request body.
 * @param {Object} headers Request headers.
 * @returns {Promise<Object>} Response data.
 */
export const fetchRequest = async (url, method = 'POST', body = {}, headers = {}) => {
    try {
        const upperMethod = method.toUpperCase();
        const options = {
            method: upperMethod,
            headers: {
                'Content-Type': 'application/json',
                ...headers,
            },
        };

        if (!['GET', 'HEAD'].includes(upperMethod) && body) {
            const contentType = options.headers['Content-Type'];

            if (contentType?.includes('application/json')) {
                options.body = JSON.stringify(body);
            } else if (contentType?.includes('application/x-www-form-urlencoded')) {
                options.body = new URLSearchParams(body).toString();
            } else if (contentType?.includes('multipart/form-data')) {
                delete options.headers['Content-Type'];
                const formData = new FormData();
                Object.entries(body).forEach(([key, value]) => formData.append(key, value));
                options.body = formData;
            }
        }

        const response = await fetch(url, options);

        if (!response.ok) {
            throw new Error("Network response is not ok");
        }

        return response.json();

    } catch (error) {
        throw error;
    }
};

/**
 * Select2 search helper function.
 * @param {HTMLSelectElement} el Select2 element.
 * @param {Object} body Request body.
 */
export const select2Search = (el, body = {}) => {
    jQuery(el).select2({
        width: '100%',
        minimumInputLength: 3,
        dropdownAutoWidth: true,
        ajax: {
            url: wkwctobagAdminObj.ajaxurl,
            type: 'POST',
            dataType: 'json',
            processData: true,
            data: function (params) {
                body.search = params.term;
                body.nonce = wkwctobagAdminObj.nonce;
                return body;
            },
            processResults: function (response, params) {
                let data = !!response?.data?.length ? response.data : [];

                data = data.map((item) => {
                    return {
                        'id': item?.id,
                        'text': item?.text,
                    }
                });

                return {
                    results: data
                }
            }
        }
    });
}


/**
 * Search user/customers using select2 with ajax.
 * @param {HTMLSelectElement} el Select2 element.
 * @param {Array}             exclude_role Excluded user roles.
 * @param {Array}             include_role Included user roles.
 */
export const searchUser = (el, exclude_role = [], include_role = []) => {
    select2Search(el, { action: 'wkwctobag_get_searched_users', exclude_role, include_role });
}

/**
 * Search product using select2 with ajax.
 * @param {HTMLSelectElement} el Select2 element.
 */
export const searchProduct = (el) => {
    select2Search(el, { action: 'wkwctobag_get_searched_products' });
}

/**
 * Search category using select2 with ajax.
 * @param {HTMLSelectElement} el Select2 element.
 */
export const searchCategory = (el) => {
    select2Search(el, { action: 'wkwctobag_get_searched_categories' });
}

/**
 * Search Bagisto customer using select2 with ajax.
 * @param {HTMLSelectElement} el Select2 element.
 */
export const searchBagistoCustomer = (el) => {
    jQuery(el).select2({
        width: '100%',
        minimumInputLength: 3,
        dropdownAutoWidth: true,
        allowClear: true,
        placeholder: {
            id: '',
            text: '- - - Search and Select a Bagisto Customer - - -'
        },
        ajax: {
            url: wkwctobagAdminObj.ajaxurl,
            type: 'POST',
            dataType: 'json',
            processData: true,
            data: function (params) {
                return {
                    action: 'wkwctobag_get_searched_bagisto_customers',
                    search: params.term,
                    nonce: wkwctobagAdminObj.nonce
                };
            },
            processResults: function (response) {
                let data = !!response?.data?.length ? response.data : [];

                data = data.map((item) => {
                    return {
                        'id': item?.id,
                        'text': item?.text,
                    }
                });

                return {
                    results: data
                }
            }
        }
    });
}
