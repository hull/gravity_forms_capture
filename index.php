<?php 
/*
Plugin Name: Gravity Form to Analytics.js
Plugin URI: https://hull.io
Description: Submits Gravity Forms entries to Analytics as Tracks And Traits.
Version: 1.0
Author: Romain Dardour
Author URI: http://github.com/unity
License: MIT
*/

//Build a json of the form content
function hull_build_analytics_traits($form, $entry){
  $data = array();
  foreach ($form["fields"] as $field) {
    if ($field["type"] == "checkbox") {
      $value = array();
      foreach ($field["inputs"] as $input) {
        $v = $entry[$input["id"]];
        if ($v == $input["label"]) { array_push($value, $v); }
      }
    } else {
      $value = $entry[$field["id"]];
    }

    $label = strtolower(str_replace(" ", "_", trim(preg_replace("/[^a-z0-9]+/i", " ", $field["admin_label"] ?: $field["label"] ) ) ) );
    $data[$label] = $value;
  }
  return array(
    "name" => $form["title"],
    "id" => $form["id"],
    "data" => $data
  );
}

//Build Analytics.js string
function hull_analytics_snippet($form){
  $data = json_encode($form->data);
  $name = $form->name;
  $id = $form->id;
  ?>
<script type='text/javascript'>
  !(function(){
    var traits = <?=$data;?>;
    var form = jQuery.extend({}, traits, { form_name: "<?=$name;?>", form_id: "<?=$name;?>" });
    if (window.Hull) {
      Hull.ready(function(hull, me, app, org){
        hull.track("Form Submitted", form);
        hull.traits(traits);
      });
    } else {
      if (window.analytics) {
        analytics.ready(function(){
          analytics.identify(traits);
          analytics.track('Form Submitted', form);
        })
      }
    }
  })()
</script>
<?php }

//Hook up to Gravity Form confirmation to get payload.
add_action('gform_confirmation', 'hull_gform_confirmation', 10, 4 );
function hull_gform_confirmation($confirmation, $form, $entry, $is_ajax){
  $data = hull_build_analytics_traits($form, $entry);

  if (is_string($confirmation)) {
    return ($confirmation." ".hull_analytics_snippet($data));
  }
  if (is_array($confirmation) && ($form["confirmation"]["type"] == "page")) {
    $url = explode("?",$confirmation["redirect"]);
    $qs = array();
    parse_str($url[1], $qs);
    $qs["gform"] = base64_encode(json_encode($data));
    return array("redirect" => $url[0]."?".http_build_query($qs));
  }
  return $confirmation;
}

//Add analytics.js footer
add_action('wp_footer', 'hull_gform_analytics');
function hull_gform_analytics(){
  $gform = $_GET['gform'];
  if ($gform) { hull_analytics_snippet(json_decode(base64_decode($gform))); }
}
