# Outlet DB

#### Editions

##### Add New One 
    GET /api
        ?table=editions
        &type=add
        &name=Edition 1
  
##### Get All
    GET /api
        ?table=editions
        &type=get

#### Edition Menu

##### Add New One
    POST /api
        ?table=edition_menu
        &type=add
        
    Request Payload
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="edition_name"
    
    edition_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_number"
    
    2
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_name"
    
    model_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="short_name"
    
    short_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="video_button"; filename="file_to_upload_1.png"
    Content-Type: image/png
    
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="subscription_button"; filename="file_to_upload_2.png"
    Content-Type: image/png
    
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="image_button"; filename="file_to_upload_3.png"
    Content-Type: image/png
    
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF--

##### Get All
    GET /api
        ?table=edition_menu
        &type=get

#### Images Menu

##### Add New One
    POST /api
        ?table=images_menu
        &type=add
        
    Request Payload
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="edition_name"
    
    edition_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_number"
    
    2
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_name"
    
    model_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="short_name"
    
    short_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="thumbnail"; filename="file_to_upload_1.png"
    Content-Type: image/png
    
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="subscription_image"; filename="file_to_upload_2.png"
    Content-Type: image/png
    
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="download_image"; filename="file_to_upload_3.png"
    Content-Type: image/png
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="product_id"
    
    123    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="price_gbp"
    
    100    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="price_usd"
    
    150    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="price_eur"
    
    125    
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF--

##### Get All
    GET /api
        ?table=images_menu
        &type=get

#### Social Networks

##### Add New One
    POST /api
        ?table=social_networks
        &type=add
        
    Request Payload
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="name"
    
    facebook
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="url"
    
    https://facebook.com/
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="icon_color"
    
    blue
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="thumbnail_grey"; filename="file_to_upload_1.png"
    Content-Type: image/png
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF--
    
##### Get All
    GET /api
        ?table=social_networks
        &type=get

#### Subscriptions Menu

##### Add New One
    POST /api
        ?table=subscriptions_menu
        &type=add
        
    Request Payload
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="edition_name"
    
    edition_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_number"
    
    3
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_name"
    
    model_name_3
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="short_name"
    
    short_model_name_3
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="subscription_image"; filename="file_to_upload_2.png"
    Content-Type: image/png
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="product_id"
    
    product_id_1
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF--

##### Get All
    GET /api
        ?table=subscriptions_menu
        &type=get

#### Videos Menu

##### Add New One
    POST /api
        ?table=videos_menu
        &type=add
        
    Request Payload
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="edition_name"
    
    edition_name_1
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_number"
    
    3
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="model_name"
    
    model_name_3
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="short_name"
    
    short_model_name_3
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="video_title"
    
    video_title_text
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="price_gbp"
    
    100    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="price_usd"
    
    150    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="price_eur"
    
    125    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="video"; filename="file_to_upload_1.mp4"
    Content-Type: video/mp4
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF
    Content-Disposition: form-data; name="product_id"
    
    product_id_1
    
    ------WebKitFormBoundarykAdOVRwnUGoIy2QF--

##### Get All
    GET /api
        ?table=videos_menu
        &type=get
