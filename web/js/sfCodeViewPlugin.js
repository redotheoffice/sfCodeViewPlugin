if (typeof jQuery != 'undefined') {
  (function ($){
    $(function(){
      // convert all links to ajax loading links
      $('pre.source a.class_link').live('click',function(event){
        $this = $(this);
        if (event.shiftKey)
        {
          window.location = $this.attr('href');
        }
        else
        {
          if ($this.data('loaded_block'))
          {
            $this.data('loaded_block').slideToggle("fast", function(){
              $this.css('background-color',$this.data('loaded_block').is(":visible") ? '#ccc' : '');
            });
          }
          else
          {
            $.get(this.href.replace('#','/'),null,function(html){
              $html = $(html);
              $this.parent('li:first').append($html);
              $this.data('loaded_block', $html);
              $this.css('background-color','#ccc');
              // $html.data('link_ref',$this);
            });
          }
        }
        event.preventDefault();
      });
      
      // mark all blocks (pairs of '{' and '}')
      blockmark = function(event) {
        $this = $(this);
        r = /block(\d+)/;
        if (cls= r.exec($this.attr('class')))
        {
          $('span.'+cls[0]).css('background-color',(event.type == 'mouseout' ? '' : 'yellow'));
        }
      }
      $('pre.source span.blockmarker').live('mouseover',blockmark).live('mouseout',blockmark);

      //source: http://snipplr.com/view.php?codeview&id=634
      function nl2br(text){
      	text = escape(text);
      	if(text.indexOf('%0D%0A') > -1){
      		re_nlchar = /%0D%0A/g ;
      	}else if(text.indexOf('%0A') > -1){
      		re_nlchar = /%0A/g ;
      	}else if(text.indexOf('%0D') > -1){
      		re_nlchar = /%0D/g ;
      	}
      	return unescape( text.replace(re_nlchar,'<br />') );
      }

      // render tooltips in a nice way
      $('<div id="livetip"></div>').hide().appendTo('body');
      var tipTitle = '';

      $('ul.methods a').live('mouseover', function(event) {
        var $link = $(this);
        tipTitle = nl2br(this.title);
        this.title = '';
        $('#livetip')
          .html('<pre>' + tipTitle + '</pre>')
          .show();
      }).live('mouseout', function(event) {
        this.title = tipTitle;
        $('#livetip').hide();
      });      
    });
  })(jQuery);
}
