import axios from 'axios';

// Inicialización centralizada para HTTP (axios)
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

export default axios;
