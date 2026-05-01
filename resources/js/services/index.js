export { default as http } from './http';

// Ejemplo de wrapper API (opcional)
export const api = {
    getUsers() {
        return http.get('/api/users');
    }
};
