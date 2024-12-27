# Important notices on customizing language files

language.xx.json store language chunks, tokens like :token are replaced by the language handlers (language.php and language.js).
The structure MUST be the same across all language files! 
Any key is used and therefore can affect function of the whole application. Try to avoid equal values within one context if possible.
Values occasionally may be stored within databases.

Subsets display mostly in the here given order.

As of 2024-12 the ini-syntax has been replaced with json for a more comprehensible nesting and saving a couple of bytes.

## company
Holds general company info, used on pdf exports.

## permissions
Autorization levels with language specific values. Default keys are hardcoded!
Append if new modules require specific permissions

## units
Organizational units.

First item (common) is used as default for texttemplates assignments and possibly other select options, admin and office are hardcoded.
Once defined keys remain in the database, so it should be appended at best!

## skills
Can be adjusted during runtime, extend or reduce to appropriate length.

_LEVEL is a reserved key
Duties CAN resemble units but this is optional. Second level key (_DESCRIPTION) is mandatory per duty.

## documentcontext
Document contexts - defined here for language reasons.
Avoid reusing any key regardless of top level key and translation to reduce confusion.
Do not use any forbidden word as defined in config.ini.
Keys are hardcoded

* identify.casedocumentation: patients treatment process, related to a case of a medical device treatment
* identify.incident: occasionally reportable incidents, related to medical device treatment, better overview of cases
* identify.equipmentsurveillance: track equipment, each their own identifier
* identify.generalrecords: make general records appendable

## case state
Administrative case state, generates a pseudodocument to check states by given permission levels and makes unclosed records filterable by given states within overview. Checkbox options with a low level key according to documentcontext high level keys record_pseudodocument_{low level key} translation must be set in [record]

## general
All keys are hardcoded

## application
Most keys are hardcoded. Terms_of_service keys however are handled as headers and therefore require translation.

## assemble
All keys are hardcoded

## audit
All keys are hardcoded

## calendar
All keys are hardcoded

## consumables
All keys are hardcoded

## csvfilter
All keys are hardcoded

## file
All keys are hardcoded

## menu
All keys are hardcoded

## message
All keys are hardcoded

## orders
Most keys are hardcoded, orderstate can be cropped or extended.

## record
All keys are hardcoded

## risk
Most keys are hardcoded.

probabilities and damages can be modified. The number of items correspond to config.ini limits risk_acceptance_level.
preset_process and preset_risk can be cropped or extended as well.

## texttemplate
All keys are hardcoded. use.genus can be extended or cropped.

## tool
All keys are hardcoded

## user
All keys are hardcoded

## regulatory
These are the chapters of the ISO 13485 and other regulatory issues. They serve as a checklist for fulfillment regarding document assignment and can be adjusted during runtime and extended by necessary regulatory aspects

## html5_qrcode
Language mod for https://github.com/mebjas/html5-qrcode