jQuery(document).ready(function($) {

    tinymce.create('tinymce.plugins.credibility_plugin', {
        init : function(ed, url) {
            // Register command for when button is clicked
            ed.addCommand('credibility_insert_shortcode', function() {
                selected = tinyMCE.activeEditor.selection.getContent();

                if( selected ){
                    //If text is selected when button is clicked
                    //Wrap shortcode around it.
                    content =  '[ref]'+selected+'[/ref]';
                }else{
                    content =  '[ref]';
                }

                tinymce.execCommand('mceInsertContent', false, content);
            });

            // Register buttons - trigger above command when clicked
            ed.addButton('credibility_button', 
            	{
            		title : 'Add Footnote', 
            		cmd : 'credibility_insert_shortcode', 
            		image: credButton
            	}
            );
        },   
    });

    // Register our TinyMCE plugin
    // first parameter is the button ID1
    // second parameter must match the first parameter of the tinymce.create() function above
    tinymce.PluginManager.add('credibility_button', tinymce.plugins.credibility_plugin);
});