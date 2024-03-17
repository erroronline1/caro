## content
* [vendor and product management](#vendor-and-product-management)
* [order](#order)
* [users](#users)
* [text recommendations](#text-recommendations)
* [forms](#forms)
* [records](#records)

### vendor and product management

```mermaid
graph TD;
    manage_vendors((manage vendors))-->edit_vendor[edit existing vendor];
    manage_vendors-->new_vendor[new vendor];
    edit_vendor-->add_vinfo["add documents,
    update info,
    set pricelist filter,
    trade goods filter"];
    new_vendor-->add_vinfo;
    add_vinfo-->import_pricelist[import pricelist];
    import_pricelist-->delete_all_products[delete all products];
    delete_all_products-->has_docs2{"product
    has documents,
    been incorporated,
    had samplecheck
    (protected)"};
    has_docs2-->|yes|update[update based on ordernumber];
    has_docs2-->|no|delete[delete];
    delete-->|reinserted from pricelist|apply_trade[apply trade good filter];
    apply_trade-->orderable(orderable);
    delete-->|not in pricelist|inorderable(not available in orders)
    update-->apply_trade;

    manage_products((manage products))-->edit_product[edit existing product];
    manage_products-->add_product[add new product];
    add_product-->select_vendor[(select vendor)];
    select_vendor-->add_pinfo["Add documents,
    update info"];
    add_pinfo-->known_vendor;

    edit_product-->add_pinfo["add documents,
    update info"];
    known_vendor{vendor in database}-->|yes|add_pinfo;
    known_vendor-->|no|new_vendor
    edit_product-->delete_product(delete product);
    delete_product-->has_docs{"product
    has documents,
    been incorporated,
    had samplecheck
    (protected)"};
    has_docs-->|no|product_deleted["product
    deleted"];
    has_docs-->|yes|product_inactive["deactivate
    product"]
    product_deleted-->inorderable;
    product_inactive-->inorderable;
    edit_product-->product_inactive;
```
[content](#content)

### order

```mermaid
graph TD;
    new_order((new order))-->search_products[(search products)];
    search_products-->product_found{product found};
    product_found-->|yes|add_product[add product to order];
    new_order-->add_manually[add manually];
    product_found-->|no|add_manually;
    product_found-->|no|manage_products((manage products));
    add_manually-->add_product;
    add_product-->search_products;
    add_product-->add_info["set unit,
    justification,
    add files"];
    add_info-->approve_order{approve order};
    approve_order-->|by signature|approved_orders(("approved orders,
    only from own unit
    unless admin
    or purchase"));
    approve_order-->|by pin|approved_orders;
    approve_order-->|no|prepared_orders(("prepared orders,
    only from own unit
    unless admin"));

    approved_orders-->process_order{process order};
    process_order-->disapprove[disapprove];
    disapprove-->append_message[append message];
    append_message-->message_unit[message all unit members];
    disapprove-->message_unit;
    message_unit-->prepared_orders;

    process_order-->|not incorporated|incorporate;
    incorporate-->incorporate_similar{"similar
    products"};
    incorporate_similar-->|yes|select_similar["select similar,
    append data"];
    select_similar-->productdb[(product database)]
    incorporate_similar-->|no|insert_data[insert data];
    insert_data-->productdb[(product database)]

    process_order-->|sample check required|sample_check[sample check];
    sample_check-->productdb[(product database)]

    process_order-->mark[mark];
    mark-->|processed|auto_delete[auto delete after X days];
    mark-->|retrieved|auto_delete;
    mark-->|archived|delete[delete manually];
    process_order-->|delete|delete;
    delete-->delete_permission{"permission
    to delete"};
    delete_permission-->|is admin|order_deleted(order deleted);
    delete_permission-->|is unit member|order_deleted;
    delete_permission-->|purchase member, unprocessed order|order_deleted;
    delete_permission-->|purchase member, processed order|approved_orders;
    
    process_order-->|add info|process_order;
    process_order-->message((message user))

    prepared_orders-->mark_bulk{"mark orders
    for approval"};
    mark_bulk-->|yes|approve_order;
    mark_bulk-->|no|prepared_orders;
    prepared_orders-->add_product;
```
[content](#content)

### users

```mermaid
graph TD;
    application((application))-->login[login];
    login-->scan_code;
    scan_code{scan code}-->user_db[(user database)];
    user_db-->|found|logged_in[logged in];
    user_db-->|not found|login;
    logged_in-->manage_users((manage users));
    manage_users-->new_user[new user];
    manage_users-->edit_user[edit user];
    new_user-->user_settings["set name, authorization,
    unit, photo, order auth pin,
    login token, user documents"];
    edit_user-->user_settings;
    user_settings-->export_token[export token];
    export_token-->user(((user)));
    user-->login;
    user_settings-->user;

    logged_in-->own_profile((profile));
    own_profile-->profile["view information,
    renew photo"];
    profile-->user;

    edit_user-->delete_user[delete user];
    delete_user-->user;

    user-->|has pin|orders((approve orders))
    user-->|authorized|authorized(("see content based
    on authorization"))
    user-->|units|units(("see content based
    on units"))
```
[content](#content)

### text recommendations

```mermaid
graph TD;
    textrecommendation(("text
    recommendation")) -->select[select template];
    select -->chunks[(chunks)];
    chunks-->|get recent by name|display["display template
    and inputs"];
    display -->|input|render(rendered text);

    managechunks(("manage
    text chunks")) -->select2["select recent
    by name or new"];
    managechunks(("manage
    text chunks")) -->select3["select any or new"];
    select2-->chunks2[(chunks)];
    select3-->chunks2;
    chunks2 -->editchunk[edit chunk];
    editchunk -->type{type};
    type -->|replacement|chunks2;
    type -->|text|chunks2;
    
    managetemplates(("manage
    text templates")) -->select4["select recent
    by name or new"];
    managetemplates(("manage
    text chunks")) -->select5["select any or new"];
    select4-->chunks3[(chunks)];
    select5-->chunks3;
    chunks3 -->edittemplate[edit template];
    edittemplate -->|add template|chunks3;
```
[content](#content)

### forms ###

```mermaid
graph TD;
    manage_components(("manage
    components"))-->|new component|edit_component["edit content,
    add widgets,
    reorder"];
    manage_components(("manage
    components"))-->|existing component|edit_component;
    edit_component-->|save|new_forms_database[("append new dataset to
    forms database")];

    manage_forms(("manage
    forms"))-->|new form|edit_form["edit form,
    reorder components"];
    manage_forms-->|existing form|edit_form;
    edit_form-->add_component[add component];
    add_component-->forms_database[(forms database)];
    forms_database-->|latest unhidden component|edit_form;
    edit_form-->|save|new_forms_database;

    manage_bundles(("manage
    bundles"))-->|new bundle|edit_bundle["edit bundle"];
    manage_bundles-->|existing bundle|edit_bundle;
    edit_bundle-->add_form[add form];
    add_form-->forms_database2[(forms database)];
    forms_database2-->|latest unhidden form|edit_bundle;
    edit_bundle-->|save|new_forms_database

    new_forms_database-->returns("returns only latest dataset on request
    if named item is not hidden")
```
[content](#content)

### records ###

```mermaid
graph TD;
    records((records))-->identifiersheet(("create
    identifier
    sheet"));
    identifiersheet-->input[input data];
    input-->|generate|print("print sheet,
    handout to workmates");

    records-->fillform((fill out form));
    fillform-->selectform[select form];
    selectform-->forms[(forms)];
    forms-->|get recent by name|displayform[display form];
    displayform-->inputdata[add data];
    inputdata-->|input new dataset with form name|recorddb[(record database)];
    displayform-->idimport[import by identifier];
    idimport-->recorddb2[(record database)];;
    recorddb2-->selectbyid[retrieve all with identifier];
    selectbyid-->|render last appended data|inputdata;

    print-.->idimport;

    records-->summaries((record summary));
    summaries-->recorddb3[(record database)]
    recorddb3-->displayids[display identifier];
    displayids-->|select|summary[display summary];
    summary-->export[export];
    export-->pdf("summary as pdf,
    attached files");
    summary-->matchbundles[match with form bundles];
    matchbundles-->missing{missing form};
    missing-->|yes|appenddata[append form];
    appenddata-->forms;
    missing-->|no|nonemissing(status message);
```
[content](#content)

