<ul class="form-field-list amender-preferences">

	<?php

    $options = array(
	    'tailwind' => array(
		    'label' => __('Tailwind', 'microthemer'),
		    'items' => array(
			    'tailwind' => array(
				    'label' => __('Enable support for Tailwind CSS', 'microthemer'),
				    'explain' => __('Enable this if you want to use Tailwind utility classes anywhere in your site\'s HTML. Amender will compile the associated Tailwind CSS styles when you view a page as a logged in administrator, thus eliminating the need for a node.js build process.' , 'microthemer')
			    ),
		    )
	    ),
	    'modify' => array(
		    'label' => __('Amendments', 'microthemer'),
		    'items' => array(
			    'default_amender_event' => array(
				    'label' => __('Default modification point', 'microthemer'),
				    'explain' => __('Set the default point at which HTML changes should apply. "serverHTMLReady" is good for SEO and avoiding layout shifts when the page loads, but in rare cases non-standard HTML can become malformed. Check the display and functionality of your page carefully when applying server-side HTML changes.' , 'microthemer'),
				    'combobox' => 'default_amender_event',
				    'is_text' => 1,
				    'one_line' => 1,
			    ),
		    )
	    ),
    );

	// output
	echo $this->preferences_grid($options, 'main-preferences-grid');
	?>
</ul>

