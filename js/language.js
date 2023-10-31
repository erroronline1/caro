import {
    api
} from '../js/api.js';

api.send('get', {
    request: 'lang_getall'
}, (data) => {
    window.LANGUAGEFILE = data;
});

class Lang {
    /*
    language files have a context level and their chunks
    :tokens can be passed as a named array to be substituted (like nifty pdo prepared statements)
    chunks can be accessed by context.chunk with the period as separator (like nifty javascript objects)
    */
    constructor() {}
    GET(request, replace = {}) {
        request = request.split('.');
        if (!(request[0] in LANGUAGEFILE) || !(request[1] in LANGUAGEFILE[request[0]])) {
            return 'undefined language';
        }
        let result = LANGUAGEFILE[request[0]][request[1]]
        for (const [pattern, replacement] of Object.entries(replace)) {
            result = result.replaceAll(pattern, replacement);
        }
        return result;
    }
}
export var LANG = new Lang();