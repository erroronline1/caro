import {
    api
} from '../js/api.js';
import {
    _
} from '../libraries/erroronline1.js';

await api.application('get', 'language');
// assignment of variable needs suprisingly long and i have not been able to manage this reliable with await
await _.sleep(50);

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