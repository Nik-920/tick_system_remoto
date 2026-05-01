// Ejemplo mínimo de store sin framework
const state = {
    currentUser: null,
};

export function getState() {
    return state;
}

export function setCurrentUser(user) {
    state.currentUser = user;
}

export default { getState, setCurrentUser };
