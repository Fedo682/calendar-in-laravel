import axios from 'axios';
import type { AxiosStatic } from 'axios';

declare global {
    interface Window {
        axios: AxiosStatic;
    }
}

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Next, we will attach the token value to every request this application
 * makes so we can protect against CSRF attacks. The token gets reset
 * every time a new response is received by the server.
 */
const token = document.head.querySelector<HTMLMetaElement>(
    'meta[name="csrf-token"]',
);

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error(
        'CSRF token not found: https://laravel.com/docs/csrf#protecting-tos',
    );
}
