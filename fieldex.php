<?php
/*
Plugin Name: Fieldex
Plugin URI: https://github.com/allanchristiancarlos/fieldex
Description: Creates filters and columns for the admin
Version: 0.1.0
Author: Allan Christian Carlos
Author URI: https://github.com/allanchristiancarlos
*/

if (!defined('ABSPATH')) {
    die();
}

function fieldex_acf_notice()
{
    if (class_exists('acf')) {
        return;
    }
    ?>
    <div class="error notice is-dismissible"> 
        <p><strong>Fieldex Plugin Error:</strong> Please install Advanced Custom Fields Plugin</button>
    </div>
    <?php
}

add_action('admin_notices', 'fieldex_acf_notice');

if (!class_exists('acf')) {
    return;
}

function fieldex_init()
{
    global $wp_post_types;

    foreach ($wp_post_types as $post_type => $post_type_options) {
        // No fieldex settings
        if (empty($post_type_options->fieldex)) {
            continue;
        }

        $fieldex        = wp_parse_args($post_type_options->fieldex, array(
            'columns_order' => array(),
            'columns'       => array()
        ));

        $fields                 = $fieldex['fields'];
        $columns_order          = $fieldex['columns_order'];
        $sortable_columns_types = fieldex_get_sortable_field_types();
        $columns                = array();
        $sortable_fields        = array();

        foreach ($fields as $k => $v) {
            // Check if field has settings
            $has_settings = is_array($v);

            // Make default field settings
            $field_settings  = array(
                'label'       => '',
                'sortable'    => 1,
                'show_column' => 1
            );

            if ($has_settings) {
                $field_settings = wp_parse_args($v, $field_settings);
            }

            // Get the field key
            $field_key = $has_settings ? $k : $v;
            // Get the field object
            $field = get_field_object($field_key);
            // Set the label of the column
            
            if ($field_settings['show_column']) {
                $columns[$field_key] = !empty($field_settings['label']) ? $field_settings['label'] : $field['label'];
            }

            // Check if the field is sortable
            if (in_array($field['type'], $sortable_columns_types) && $field_settings['sortable']) {
                // Get sortable fields
                $sortable_fields[$field_key] = $field_key;
            }
        }

        // Convert columns array to string for the create_function
        ob_start();
        var_export($columns);
        $columns_string = ob_get_clean();

        // Convert columns order array to string for the create_function
        ob_start();
        var_export($columns_order);
        $columns_order_string = ob_get_clean();

        // Convert fields_sortable order array to string for the create_function
        ob_start();
        var_export($sortable_fields);
        $columns_sortable_string = ob_get_clean();

        // Create dynamic function for add the sortable columns
        add_filter("manage_edit-{$post_type}_sortable_columns", create_function('$columns', '
            return '. $columns_sortable_string .';
        '));

        // Create dynamic function to add the columns in the post type table
        add_filter("manage_edit-{$post_type}_columns", create_function('$columns', '
            $columns_order = '.$columns_order_string.';
            $columns       = array_merge($columns,'. $columns_string .');
            $new_columns   = array();

            if (COUNT($columns_order) > 0) {
                foreach ($columns_order as $column) {
                    $new_columns[$column] = $columns[$column];
                } 
            } else {
                $new_columns = $columns;
                $date        = $new_columns["date"];
                unset($new_columns["date"]);
                $new_columns["date"] = $date;
            }

            return $new_columns;
        '));


        // Create dynamic function to add the content of the column
        add_action("manage_{$post_type}_posts_custom_column", create_function('$column, $post', '
            $field = get_field_object($column);

            if (isset($field["id"])) {
                $type = $field["type"];
                $value = get_field($column, $post);
                ob_start();
                do_action("fieldex_render_field", $value, $column, $post, $field);
                do_action("fieldex_render_field/type={$type}", $value, $column, $post, $field);
                $html = apply_filters("fieldex_html_field", ob_get_clean(), $value, $column, $post, $field);
                echo apply_filters("fieldex_html_field/type={$type}", $html, $value, $column, $post, $field);
            }
        '), 10, 2);
    }
}

add_action('init', 'fieldex_init', 999999);

function fieldex_pre_get_post(&$query)
{
    global $wp_post_types;

    if (!$query->is_main_query() || !is_admin()) {
        return;
    }

    $post_type        = $query->get('post_type');
    $post_type_object = $wp_post_types[$post_type];

    if (!isset($post_type_object->fieldex)) {
        return;
    }

    

    /**
     * Start Ordering
     */
    if (!empty($query->get('orderby'))) {
        $field_key = $query->get('orderby');
        $field     = get_field_object($field_key);
        
        if (!isset($field['name'])) {
            return;
        }

        $query->set('orderby', 'meta_value');
        $query->set('meta_key', $field['name']);
    }
    /**
     * End ordering
     */
    

    /**
     * Start Filters
     */
    if (!isset($_GET['acf'])) {
        return;
    }

    $filterable_fields = fieldex_get_filterable_fields($post_type);
        
    $acf = isset($_GET['acf']) ? $_GET['acf'] : array();

    foreach ($filterable_fields as $field_key) {
        $field = get_field_object($field_key);
        $value = isset($acf[$field_key]) ? $acf[$field_key] : null;
        do_action_ref_array("fieldex_filter_pre_get_post/type={$field['type']}", array(&$query, $field, $value));
    }
    /**
     * Start Filters
     */
}

add_action('pre_get_posts', 'fieldex_pre_get_post', 10, 1);


function fieldex_filter_text_field(&$query, $field, $value)
{
    if (empty($value) || is_array($value)) {
        return;
    }

    $meta_query = $query->get('meta_query') ? $query->get('meta_query') : array();

    $meta_query[] = array(
        'key'     => $field['name'],
        'value'   => $value,
        'compare' => 'LIKE'
    );

    $query->set('meta_query', $meta_query);
}

add_action('fieldex_filter_pre_get_post/type=text', 'fieldex_filter_text_field', 10, 3);
add_action('fieldex_filter_pre_get_post/type=textarea', 'fieldex_filter_text_field', 10, 3);
add_action('fieldex_filter_pre_get_post/type=url', 'fieldex_filter_text_field', 10, 3);
add_action('fieldex_filter_pre_get_post/type=email', 'fieldex_filter_text_field', 10, 3);


function fieldex_filter_exact(&$query, $field, $value)
{
    if (is_array($value) && $field['type'] != 'true_false') {
        return;
    }

    $value = (array) $value;

    if (COUNT($value) == 1 && $value[0] == '') {
        return;
    }

    $meta_query = $query->get('meta_query') ? $query->get('meta_query') : array();

    $meta_query[] = array(
        'key'     => $field['name'],
        'value'   => $value,
        'compare' => 'IN'
    );

    $query->set('meta_query', $meta_query);
}

add_action('fieldex_filter_pre_get_post/type=user', 'fieldex_filter_exact', 10, 3);
add_action('fieldex_filter_pre_get_post/type=radio', 'fieldex_filter_exact', 10, 3);
add_action('fieldex_filter_pre_get_post/type=select', 'fieldex_filter_exact', 10, 3);
add_action('fieldex_filter_pre_get_post/type=true_false', 'fieldex_filter_exact', 10, 3);

function fieldex_filter_array_values(&$query, $field, $value)
{
    
    if (!is_array($value)) {
        return;
    }

    $val = array_filter($value);

    if (empty($val)) {
        return;
    }

    $meta_query = $query->get('meta_query') ? $query->get('meta_query') : array();

    $mq = array(
        'relation' => 'OR'
    );

    foreach ($value as $val) {
        $count = strlen($val);
        $mq[] = array(
            'key'     => $field['name'],
            'value'   => "s:{$count}:\"{$val}\";",
            'compare' => 'LIKE'
        );
    }

    $meta_query[] = $mq;

    $query->set('meta_query', $meta_query);

}

add_action('fieldex_filter_pre_get_post/type=checkbox', 'fieldex_filter_array_values', 999, 3);
add_action('fieldex_filter_pre_get_post/type=select', 'fieldex_filter_array_values', 999, 3);
add_action('fieldex_filter_pre_get_post/type=user', 'fieldex_filter_array_values', 999, 3);

function fieldex_filter_range_values(&$query, $field, $value)
{
    if (!is_array($value)) {
        return;
    }

    $val = array_filter($value);

    if (empty($val)) {
        return;
    }

    $meta_query = $query->get('meta_query') ? $query->get('meta_query') : array();

    if (!empty($val['start']) && !empty($val['end'])) {
        $meta_query[] = array(
            'key'     => $field['name'],
            'value'   => array($val['start'], $val['end']),
            'compare' => 'BETWEEN'
        );
    } elseif (!empty($val['start'])) {
        $meta_query[] = array(
            'key'     => $field['name'],
            'value'   => $val['start'],
            'compare' => '='
        );
    }

    $query->set('meta_query', $meta_query);

}

add_action('fieldex_filter_pre_get_post/type=number', 'fieldex_filter_range_values', 10, 3);
add_action('fieldex_filter_pre_get_post/type=date_picker', 'fieldex_filter_range_values', 10, 3);
add_action('fieldex_filter_pre_get_post/type=date_time_picker', 'fieldex_filter_range_values', 10, 3);
add_action('fieldex_filter_pre_get_post/type=time_picker', 'fieldex_filter_range_values', 10, 3);

function fieldex_filter_taxonomy(&$query, $field, $value)
{
    if (!is_array($value)) {
        return;
    }

    $val = array_filter($value);

    if (empty($val)) {
        return;
    }

    if ($field['save_terms']) {
        $tax_query = $query->get('tax_query') ? $query->get('tax_query') : array();

        $tax_query[] = array(
            'taxonomy' => $field['taxonomy'],
            'terms'    => $val,
        );

        $query->set('tax_query', $tax_query);
    } else {
        fieldex_filter_array_values($query, $field, $val);
    }
}

add_action('fieldex_filter_pre_get_post/type=taxonomy', 'fieldex_filter_taxonomy', 10, 3);

function fieldex_render_field_select($value, $field_key, $post_id, $field)
{
    $multiple = $field['multiple'];

    if (is_array($value)) {
        $choices = $field['choices'];
        foreach ($value as $val) {
            if (isset($choices[$val])) {
                echo '- ' . $choices[$val] . '<br/>';
            }
        }
    } else {
        $choices = $field['choices'];
        if (isset($choices[$value])) {
            echo $choices[$value];
        }
    }
}

add_action('fieldex_render_field/type=select', 'fieldex_render_field_select', 10, 4);

function fieldex_render_field_text($value, $field_key, $post_id, $field)
{
    echo $value;
}

add_action('fieldex_render_field/type=text', 'fieldex_render_field_text', 10, 4);

function fieldex_get_filterable_field_types()
{
    return apply_filters('fieldex_sortable_types', array(
        'text',
        'textarea',
        'number',
        'date_picker',
        'date_time_picker',
        'time_picker',
        'url',
        'radio',
        'email',
        'true_false',
        'taxonomy',
        'user',
        'select',
        'checkbox',
    ));
}

function fieldex_get_sortable_field_types()
{
    return apply_filters('fieldex_sortable_types', array(
        'text',
        'textarea',
        'wysiwyg',
        'number',
        'date_picker',
        'date_time_picker',
        'time_picker',
        'url',
        'radio',
        'email',
    ));
}

function fieldex_render_field_textarea($value, $field_key, $post_id, $field)
{
    echo $value;
}

add_action('fieldex_render_field/type=textarea', 'fieldex_render_field_textarea', 10, 4);

function fieldex_render_field_number($value, $field_key, $post_id, $field)
{
    echo $value;
}

add_action('fieldex_render_field/type=number', 'fieldex_render_field_number', 10, 4);

function fieldex_render_field_email($value, $field_key, $post_id, $field)
{
    echo sprintf('<a href="mailto:%s">%s</a>', $value, $value);
}

add_action('fieldex_render_field/type=email', 'fieldex_render_field_email', 10, 4);

function fieldex_render_field_url($value, $field_key, $post_id, $field)
{
    echo sprintf('<a href="%s" target="_new">%s</a>', $value, $value);
}

add_action('fieldex_render_field/type=url', 'fieldex_render_field_url', 10, 4);

function fieldex_render_field_wysiwyg($value, $field_key, $post_id, $field)
{
    echo $value;
}

add_action('fieldex_render_field/type=wysiwyg', 'fieldex_render_field_wysiwyg', 10, 4);

function fieldex_render_field_image($value, $field_key, $post_id, $field)
{
    $id = get_field($field_key, $post_id, false);
    echo wp_get_attachment_image( $id, 'thumbnail', null, array(
        'style' => 'width: 64px;height: auto;'
    ));
}

add_action('fieldex_render_field/type=image', 'fieldex_render_field_image', 10, 4);

function fieldex_render_field_file($value, $field_key, $post_id, $field)
{
    $id = get_field($field_key, $post_id, false);
    echo str_replace('href', 'target="_new" href', wp_get_attachment_link($id));
}

add_action('fieldex_render_field/type=file', 'fieldex_render_field_file', 10, 4);

function fieldex_render_field_checkbox($value, $field_key, $post_id, $field)
{
    $items   = (array) $value;
    $choices = $field['choices'];

    foreach ($items as $item) {
        if (isset($choices[$item])) {
            echo "- {$choices[$item]}<br/>";
        }
    }
}

add_action('fieldex_render_field/type=checkbox', 'fieldex_render_field_checkbox', 10, 4);


function fieldex_get_filterable_fields($post_type)
{
    global $wp_post_types;

    if (!fieldex_is_fieldex_post_type($post_type)) {
        return array();
    }

    $object = $wp_post_types[$post_type];
    $fields = array();

    foreach ($object->fieldex['fields'] as $k => $v) {
        $field_key = is_array($v) ? $k : $v;

        $settings = array(
            'filterable' => 1
        );

        if (is_array($v)) {
            $settings = wp_parse_args($v, $settings);
        }

        $field = get_field_object($field_key);

        if ($settings['filterable'] == true && in_array($field['type'], fieldex_get_filterable_field_types($post_type))) {
            $fields[] = $field_key;
        }
    }

    return $fields;
}


function fieldex_render_field_radio($value, $field_key, $post_id, $field)
{
    $choices = $field['choices'];

    if (isset($choices[$value])) {
        echo "{$choices[$value]}";
    }
}

add_action('fieldex_render_field/type=radio', 'fieldex_render_field_radio', 10, 4);

function fieldex_render_field_true_false($value, $field_key, $post_id, $field)
{
    echo $value ? "Yes" : "No";
}

add_action('fieldex_render_field/type=true_false', 'fieldex_render_field_true_false', 10, 4);

function fieldex_render_field_page_link($value, $field_key, $post_id, $field)
{
    if (empty($value)) {
        return;
    }
    $id = get_field($field_key, $post_id, false);
    echo sprintf('<a href="%s" target="_new">%s</a>', get_permalink($id), get_the_title($id));
}

add_action('fieldex_render_field/type=page_link', 'fieldex_render_field_page_link', 10, 4);

function fieldex_render_field_color_picker($value, $field_key, $post_id, $field)
{
    if (empty($value)) {
        return;
    }
    $id = get_field($field_key, $post_id, false);
    echo sprintf('<span style="width: 16px;height: 16px;background-color: %s;display: inline-block;"></span> %s', $id, $id);
}

add_action('fieldex_render_field/type=color_picker', 'fieldex_render_field_color_picker', 10, 4);

function fieldex_render_field_date_picker($value, $field_key, $post_id, $field)
{
    if (empty($value)) {
        return;
    }
    $date = get_field($field_key, $post_id, false);
    echo date($field['display_format'], strtotime($date));
}

add_action('fieldex_render_field/type=date_picker', 'fieldex_render_field_date_picker', 10, 4);
add_action('fieldex_render_field/type=date_time_picker', 'fieldex_render_field_date_picker', 10, 4);
add_action('fieldex_render_field/type=time_picker', 'fieldex_render_field_date_picker', 10, 4);


function fieldex_render_field_user($value, $field_key, $post_id, $field)
{
    if (empty($value)) {
        return;
    }
    $ids = get_field($field_key, $post_id, false);

    if (is_array($ids)) {
        foreach ($ids as $id) {
            $user = get_userdata($id);
            echo "- ";
            echo sprintf('<a href="%s" target="_new">%s</a>', get_edit_user_link($id), $user->display_name);
            echo "<br/>";
        }
    } else {
        $user = get_userdata($ids);
        echo sprintf('<a href="%s" target="_new">%s</a>', get_edit_user_link($ids), $user->display_name);
    }
}

add_action('fieldex_render_field/type=user', 'fieldex_render_field_user', 10, 4);

function fieldex_render_field_page_taxonomy($value, $field_key, $post_id, $field)
{
    if (empty($value)) {
        return;
    }
    $ids = get_field($field_key, $post_id, false);
    
    foreach ($ids as $id) {
        $term = get_term($id, $field['taxonomy']);
        echo '- ';
        echo $term->name;
        echo "<br/>";
    }
}

add_action('fieldex_render_field/type=taxonomy', 'fieldex_render_field_page_taxonomy', 10, 4);


function fieldex_render_field_gallery($value, $field_key, $post_id, $field)
{
    $images = $value;
    $max = 4;
    $count = 0;
    foreach ((array) $images as $image) {
        if ($count == $max) {
            break;
        }
        echo wp_get_attachment_image($image['ID'], 'thumbnail', null, array(
            'style' => 'width: 64px;height: auto;'
        ));
        $count++;
    }
}

add_action('fieldex_render_field/type=gallery', 'fieldex_render_field_gallery', 10, 4);

function fieldex_is_fieldex_post_type($post_type)
{
    global $wp_post_types;

    return !empty($wp_post_types[$post_type]->fieldex);
}

function fieldex_filters()
{
    global $post_type, $wp_post_types;
    
    if (!fieldex_is_fieldex_post_type($post_type)) {
        return;
    }

    $fields = fieldex_get_filterable_fields($post_type);

    if (empty($fields)) {
        return;
    }
    ?>
    <a href="#" id="js-fieldex-toggle-filter" class="button button-primary" style="margin: 1px 8px 0 0;float:right;">Advanced Filters</a>

    <div class="fieldex-form" id="js-fieldex-form">
        <div class="fieldex-form-inner acf-fields">
            <div class="acf-field" style="text-align: right;background: #f3f3fa;padding: 8px;">
                <input type="button" value="Reset" class="button" id="js-fieldex-reset-button">
                <input type="submit" value="Search" class="button button-primary">
            </div>

            <?php
            $input = array();
            
            if (isset($_GET['acf']) && is_array($_GET['acf'])) {
                $input = $_GET['acf'];
            }

            foreach ($fields as $key => $field_key) {
                $field             = get_field_object($field_key, false, false, false);
                $field['required'] = 0;
                $field             = apply_filters('fieldex_filter_render_field', $field, $post_type);
                $field             = apply_filters('fieldex_filter_render_field/type=' . $field['type'], $field, $post_type);
                $field['value']    = is_array($field['value']) ? array() : '';
                $label             = $field['label'];

                if (isset($input[$field_key])) {
                    $field['value'] = $input[$field_key];
                }

                if (isset($wp_post_types[$post_type]->fieldex['fields'][$field_key])) {
                    if (isset($wp_post_types[$post_type]->fieldex['fields'][$field_key]['label'])) {
                        $field['label'] = $wp_post_types[$post_type]->fieldex['fields'][$field_key]['label'];
                    }
                }

                // Remove instructions
                $field['instructions'] = '';
                $fields[$key] = $field;
            }
            acf_render_fields( 0, apply_filters('fieldex_filter_fields', $fields, $post_type, $input) );
            ?>
        </div>
    </div>

    <?php
}

add_action('restrict_manage_posts', 'fieldex_filters');

function fieldex_render_filter_as_text($field, $post_type)
{
    $field['type']      = 'text';
    $field['readonly']  = 0;
    $field['disabled']  = 0;
    $field['prepend'] = '';
    $field['append'] = '';
    $field['maxlength'] = "";
    return $field;
}

add_filter('fieldex_filter_render_field/type=email', 'fieldex_render_filter_as_text', 10, 2);
add_filter('fieldex_filter_render_field/type=url', 'fieldex_render_filter_as_text', 10, 2);
add_filter('fieldex_filter_render_field/type=textarea', 'fieldex_render_filter_as_text', 10, 2);

function fieldex_admin_body_class($classes)
{
    global $post_type;

    if (fieldex_is_fieldex_post_type($post_type)) {
        $classes .= 'fieldex';

        $object = get_post_type($post_type);
    }

    return $classes;
}

add_filter('admin_body_class', 'fieldex_admin_body_class');

function fieldex_admin_assets()
{
    global $post_type;

    if (!fieldex_is_fieldex_post_type($post_type)) {
        return;
    }

    wp_register_script('fieldex', plugin_dir_url(__FILE__). 'assets/js/fieldex.js', array('jquery'), null, true);
    wp_enqueue_script('fieldex');
    
    wp_register_style('fieldex', plugin_dir_url(__FILE__). 'assets/css/fieldex.css');
    wp_enqueue_style('fieldex');

}

add_action('admin_enqueue_scripts', 'fieldex_admin_assets');

function fieldex_load_acf_header()
{
    $post_type = filter_input(INPUT_GET, 'post_type');
    
    if (!fieldex_is_fieldex_post_type($post_type)) {
        return;
    }

    acf_form_head();
}

add_action('load-edit.php', 'fieldex_load_acf_header');

function fieldex_filter_range_fields($fields, $post_type, $input)
{
    foreach ($fields as $key => $field) {
        if ($field['type'] == 'date_picker' || $field['type'] == 'date_time_picker' || $field['type'] == 'number' || $field['type'] == 'time_picker') {
            unset($fields[$key]);
            $field['wrapper']['width'] = '50%';
            $field['value']            = '';
            $start                     = $field;
            $end                       = $field;
            $start['label']            .= ' Start';
            $start['value']            = !empty($input[$field['key']]['start']) ? $input[$field['key']]['start'] : '';
            $end['value']              = !empty($input[$field['key']]['end']) ? $input[$field['key']]['end'] : '';
            $end['label']              .= ' End';

            $start['_input'] = "acf[{$field['key']}][start]";
            $end['_input'] = "acf[{$field['key']}][end]";

            $fields[] = $start;
            $fields[] = $end;
        }
        if ($field['type'] == 'radio' || $field['type'] == 'select') {
            $choices = array('' => 'All');
            $choices += $fields[$key]['choices'];
            $fields[$key]['choices'] = $choices;
        }
        if ($field['type'] == 'true_false') {
            $fields[$key]['type'] = 'checkbox';
            $fields[$key]['toggle'] = false;
            $fields[$key]['layout'] = 'horizontal';
            $fields[$key]['choices'] = array(
                1 => 'Yes',
                0 => 'No'
            );
        }
    }

    return $fields;
}

add_filter('fieldex_filter_fields', 'fieldex_filter_range_fields', 10, 3);
