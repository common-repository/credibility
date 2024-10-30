<?php
/*
 * Plugin Name: Credibility
 * Plugin URI: http://builtforagility.com
 * Description: Allows you to easily add footnotes anywhere in your theme by using the credibility_footnotes_display function.
 * Version: 1.22
 * Author: Eric Binnion
 * Author URI: http://superhero.io
 */

class mbm_credibility_footnotes {
	private $options;

	public function __construct() {
		$this->options = get_option( 'mbm_credibility_options' );

		// Activation hooks
		register_activation_hook( __FILE__, array( $this, 'mbm_activated' ) );
		add_filter( 'admin_init', array($this, 'activation_redirect') );

		// Init hooks
		add_action( 'admin_menu', array($this, 'credibility_options') );
		add_action( 'admin_init', array($this, 'credibility_settings') );
		add_action('init', array( $this, 'credibility_shortcode_button_init') );

		// Display hooks
		add_filter( 'the_content', array($this, 'display_filter') );
		add_action( 'save_post', array($this, 'build_footnotes') );
		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_frontend_css') );
		add_action( 'admin_head', array($this, 'admin_header') );
	}

	public function mbm_activated() {
		// Case for plugin being activated and options not being set before.
		if( $this->options === false ){
			update_option(
				'mbm_credibility_options', 
				array(
					'eop' => '1', 
					'css' => '1',
					'header' => 'References and Footnotes',
					'first_active' => '1',
					'attribution' => '0',
					'attribution_message' => "I'm committed to making my website credible."
				)
			);
		}
	}

	public function activation_redirect() {
		if( isset($this->options['first_active']) ){
			unset($this->options['first_active']);
			$this->save_updated_options();
			wp_redirect( admin_url('options-general.php?page=mbm_credibility&credibility_activated=1') );
		}
	}

	public function credibility_shortcode_button_init() {
		//Abort early if the user will never see TinyMCE
		if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') && get_user_option('rich_editing') == 'true')
			return;

		//Add a callback to regiser our tinymce plugin   
		add_filter('mce_external_plugins', array($this, 'credibility_register_tinymce_plugin') ); 

		// Add a callback to add our button to the TinyMCE toolbar
		add_filter('mce_buttons', array($this, 'credibility_add_tinymce_button') );
	}

	//This callback registers our plug-in
	public function credibility_register_tinymce_plugin($plugin_array) {
		$plugin_array['credibility_button'] = plugins_url('credibility-button.js', __FILE__);
		return $plugin_array;
	}

	//This callback adds our button to the toolbar
	public function credibility_add_tinymce_button($buttons) {
		//Add the button ID to the $button array
		$buttons[] = "credibility_button";
		return $buttons;
	}

	public function credibility_options() {
		add_options_page('Credibility', 'Credibility', 'manage_options', 'mbm_credibility', array($this, 'settings_page') );
	}

	public function credibility_settings(){
	    register_setting('mbm_credibility_options', 'mbm_credibility_options');
	}

	public function save_updated_options() {
		update_option('mbm_credibility_options', $this->options);
	}

	public function build_footnotes($post_id){
		//verify post is not a revision
		if ( !wp_is_post_revision( $post_id ) ) {
			$content = get_post_field('post_content', $post_id);

			preg_match_all('|\[ref\].*?\[/ref\]|', $content, $matches);

			if( empty( $matches[0] ) ){
				delete_post_meta($post_id, 'mbm_credibility_footnotes');
			} else {
				$i = 1;						// Incrementer for number of footnotes
				$footnotes = '<ol>'; 		// Initialize string for footnotes
				foreach ( $matches[0] as $match ){
					$temp = $match;
					$no_ref = preg_replace('#(\[ref\]|\[/ref\])#', '', $temp);

					// Build <ol> of footnotes
					$footnotes .= '<li id="note-'.$post_id.'-'.$i.'">'.$no_ref.' <a href="#return-note-'.$post_id.'-'.$i.'" class="return-link">&#8617;</a></li>';

					$i++;
				}
				$footnotes .= '</ol>';
				update_post_meta($post_id, 'mbm_credibility_footnotes', wp_kses_post($footnotes) );
			}
		}
	}

	public function enqueue_frontend_css() {
		if( $this->options['css'] == '1' )
			wp_enqueue_style( 'credibility-css', plugins_url('credibility.css', __FILE__) );
	}

	public function admin_header() { ?>
		<style>
		.mce-toolbar .mce-ico { background-size: 100%; }
		</style>

		<script>
			credButton = '<?php echo plugins_url("tinymce_button.png", __FILE__); ?>';
		</script>
	<?php }

	public function build_footnotes_display($postid, $header, $tag) {
		$footnotes = get_post_meta($postid, 'mbm_credibility_footnotes', true);

		if ( !empty($footnotes) ) { 
			$output = "<div class='credibility-footnotes'>";
				$output .= "<{$tag}>{$header}</{$tag}>";
				if( $this->options['attribution'] == '1' ) 
					$output .= "<a target='_blank' class='attribution' href='http://builtforagility.com/plugins/credibility'>{$this->options['attribution_message']}</a>";
				$output .= "{$footnotes}</div>";

			return $output;
		}
	}

	public function display_filter($content){
		global $post;
		$post_id = $post->ID;

		preg_match_all('|\[ref\].*?\[/ref\]|', $content, $matches);

		$i = 1;					// Incrementer for number of footnotes
		foreach ( $matches[0] as $match ){
			$temp = $match;
			// Remove [ref] and [/ref] to get anchor text
			$no_ref = preg_replace('#(\[ref\]|\[/ref\])#', '', $temp);
			$no_link = trim(preg_replace('|<a.*?</a>|', '', $no_ref));
			$link = trim(str_replace($no_link, '', $no_ref));

			// Build string to replace content with then replace content
			$replace = '<a class="credibility-footnote" title="'.$no_link.'" id="return-note-'.$post_id.'-'.$i.'" href="#note-'.$post_id.'-'.$i.'"><sup>['.$i.']</sup></a>';
			$content = str_replace($match, $replace, $content);

			$i++;
		}

		if ( $this->options['eop'] == '1' )
			return $content . $this->build_footnotes_display($post_id, $this->options['header'], 'h4');
		else
			return $content;
	}

	public function settings_page(){ ?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			

			<form action="options.php" method="post">
				<?php
					settings_fields( 'mbm_credibility_options' );
					do_settings_sections( __FILE__ );

					if( isset($_GET['credibility_activated']) ): ?>

						<h2>Welcome to Credibility!</h2>

						<p>Hey! Congratulations on taking the first step to making your website more credible.</p>

						<p>Here is a brief demo video on how to use credibility:</p>

						<iframe width="560" height="315" src="//www.youtube.com/embed/j8CKeFASLBw" frameborder="0" allowfullscreen></iframe>

						<p>Also, if you'd like to help other website owners make their websites more credible, just click this box and anytime you use credibility, we'll include a small link saying "I'm committed to making this website credible" or you can customize the text that shows below. (optional)</p>

						<table class="form-table">
							<tr>
								<th scope="row">Attribution On?</th>
								<td>
									<p class="description"><input type="checkbox" value="1" name="mbm_credibility_options[attribution]" <?php checked($this->options['attribution'], '1'); ?>> Yes, I'd like to help other website owners make their websites more credible.</p>
								</td>
							</tr>
						</table>


						<p>We really hope you enjoy using Credibility.</p>

						<p>
							Thanks,<br>
							-The Built for Agility Team<br>
							<a target="_blank" href="http://builtforagility.com/about/">http://builtforagility.com/about/</a>
						</p>

						<hr>
					<?php endif; ?>

				<h2>Credibility Options</h2>
				<table class="form-table">
				    <tr>
				    	<th scope="row">Attach to end of post?</th>
				    	<td>
				    		<input type="hidden" value="0" name="mbm_credibility_options[eop]">
				    		<p class="description"><input type="checkbox" value="1" name="mbm_credibility_options[eop]" <?php checked($this->options['eop'], '1'); ?>> Yes</p>

				    	</td>
				    </tr>

				    <tr>
				    	<th scope="row">Header</th>
				    	<td>
				    		<input type="text" value="<?php echo $this->options['header']; ?>" name="mbm_credibility_options[header]">

				    		<p class="description">Enter the text that you'd like to display in the header of Credibility footnotes.</p>
				    	</td>
				    </tr>

				    <tr>
				    	<th scope="row">Default Styling</th>
				    	<td>
				    		<input type="hidden" name="mbm_credibility_options[css]" value="0">
				    		<p class="description"><input type="checkbox" value="1" name="mbm_credibility_options[css]" <?php checked($this->options['css'], '1'); ?>> Check the box if you'd like to include default styling for Credibility.</p>
				    	</td>
				    </tr>


				    <?php if( !isset($_GET['credibility_activated']) ): ?>
					    <tr>
					    	<th scope="row">Attribution On?</th>
					    	<td>
					    		<input type="checkbox" value="1" name="mbm_credibility_options[attribution]" <?php checked($this->options['attribution'], '1'); ?>>

					    		<p class="description">If you'd like to help other website owners make their websites more credible, just click this box and anytime you use Credibility, we&apos;ll include a small link saying "I&apos;m committed to making this website credible", or you can customized the text below.</p>
					    	</td>
					    </tr>
					<?php endif; ?>

				    <tr>
				    	<th scope="row">Attribution Message</th>
				    	<td>
				    		<textarea class="widefat" name="mbm_credibility_options[attribution_message]"><?php echo $this->options['attribution_message']; ?></textarea>

				    		<!-- <p class="description">If you'd like to help other website owners make their websites more credible, just click this box and anytime you use Credibility, we&apos;ll include a small link saying "I&apos;m committed to making this website credible", or you can customized the text below.</p> -->
				    	</td>
				    </tr>
				</table>

		        <input type="submit" value="Save" class="button button-primary"/>
		    </form>
		</div>
	<?php
	}
}

$credibility = new mbm_credibility_footnotes();

// Here is the function to display the unordered list of footnotes
function credibility_footnotes_display($header = 'References and Footnotes', $tag = 'h4') {
	global $post;
	global $credibility;
	echo $credibility->build_footnotes_display($post->ID, $header, $tag);
}