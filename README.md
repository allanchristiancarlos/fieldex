# Fieldex
Developer plugin used with ACF for displaying fields in the post table and creating filters.

### Requirements

1. ACF 5
2. WordPress 4.2 and above.

### How to install

1. [Download](https://github.com/allanchristiancarlos/fieldex/archive/master.zip) plugin.
2. Install to WordPress site upload zip file and activate plugin.


### How to use
- Copy all the Field keys you want to include in the post table.
![alt](http://image.prntscr.com/image/81726b84aa9543de8562e5b44ff05107.png)

- In your registration of custom post type add a *fieldex* argument with a subarray of *fields* with the list of the fields you copied.
```
register_post_type('sample', array(
  'public' => true,
  'show_ui' => true,
  'fieldex' => array(
    'fields' => array(
      'field_5763b7dc8050c',
      'field_57asb7dc10A0c'
    )
   )
));
```

- Then finished! Look at your custom post type table to see if it displays the right fields.

Columns
![alt](http://image.prntscr.com/image/4b8e2a67e53642c1b0e3233768dcfa8b.png)
Filtering
![alt](http://image.prntscr.com/image/5a1b45c105264a6f9888f9021debea98.png)

### Options
```
'fieldex' => array(
    'fields' => array(
        'field_5763b7dc8050c' => array(
            'sortable' => true,
            'filterable' => true,
            'show_column' => true,
            'label' => 'My Custom Label',
        )
    )
) 
``` 

- *sortable* (Optional, Boolean, Default true)
  
  Makes the column sortable. Please note that not all field types are sortable.
  
- *filterable* (Optional, Boolean, Default true)
  
  Adds a filter to the post table "Advanced Filter" button

- *show_column* (Optional, Boolean, Default true)
  
  Shows or hides the column in the post table

- *label* (Optional, String, Default none)
  
  Creates custom label for the column name and search filter label instead of the fields label.


### Sortable Field Types
- text
- textarea
- wysiwyg
- number
- date_picker
- date_time_picker
- time_picker
- url
- radio
- email

### Limitation
- Repeater field not supported yet.
- Not all fields are sortable.

### TODO
- Repeater field support
