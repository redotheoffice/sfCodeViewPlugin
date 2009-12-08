<?php
  try {
    @use_helper('jQuery');
  } catch (Exception $e) { 
  }
  
  foreach (sfConfig::get('app_sfCodeViewPlugin_javascripts') as $javascript)
  {
    use_javascript($javascript, 'last');
  }
  foreach (sfConfig::get('app_sfCodeViewPlugin_stylesheets') as $stylesheet)
  {
    use_stylesheet($stylesheet, 'last');
  }

  // viewer and history are already escaped
  $viewer = $sf_data->get('viewer', ESC_RAW);
  $history = $sf_data->get('history', ESC_RAW);
  
  echo "<pre class='source'>".$viewer->render(url_for('sfCodeView'))."</pre>";
  echo "<div id='methodindex'>".$viewer->renderMethods(url_for('sfCodeView'));
  echo "<hr/><ul class='history'><h2>History</h2>";
  
  foreach ($history as $class)
  {
    echo "<li><a href='".url_for('sfCodeView',array('class'=>$class))."'>".$class."</a></li>";
  }
  echo "</ul></div>";