# Fieldex
Developer plugin used with ACF for displaying fields in the post table and creating filters.

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

1. *sortable* (Optional, Boolean, Default true)
  Makes the column sortable. Please note that not all field types are sortable.
  ```
  'fieldex' => array(
    'fields' => array(
      'field_5763b7dc8050c' => array(
        'sortable' => true
      )
    )
  ) 
  ``` 
2. *filterable* (Optional, Boolean, Default true)
  Adds a filter to the post table "Advanced Filter" button
  ```
  'fieldex' => array(
    'fields' => array(
      'field_5763b7dc8050c' => array(
        'filterable' => true
      )
    )
  ) 
  ```
3. *show_column* (Optional, Boolean, Default true)
  Shows or hides the column in the post table
  ```
  'fieldex' => array(
    'fields' => array(
      'field_5763b7dc8050c' => array(
        'show_column' => true
      )
    )
  ) 
  ```
4. *label* (Optional, String, Default none)
  Creates custom label for the column name and search filter label instead of the fields label.
  ```
  'fieldex' => array(
    'fields' => array(
      'field_5763b7dc8050c' => array(
        'label' => "My Custom Label"
      )
    )
  ) 
  ```

### Limitation
- Repeater field not supported yet.
- Not all fields are sortable.

### TODO
- Repeater field support
