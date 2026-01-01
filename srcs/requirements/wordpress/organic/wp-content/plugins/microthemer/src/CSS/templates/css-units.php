<ul class="form-field-list css_units">

	<?php

	$group_key = '';

	$unit_cats = array(
		'all_units' => array(
			'label' => __('All length units', 'microthemer'),
			'items' => array(
				'load_css_unit_sets' => array(
					'is_text' => 1,
					'one_line' => 1,
					'empty_after' => 1,
					'input_id' => 'css_unit_set',
					'combobox' => 'css_length_units',
					'label' => __('Set ALL length units to:', 'microthemer'),
					'explain' => __('Pixels are easier for beginners. But many consider it best practice to rem units for length properties', 'microthemer')
				)
			)
		)
	);

	// output CSS unit options
	foreach($this->preferences['my_props'] as $prop_group => $array){

		// skip if non-valid or we've removed a property group
		if ($prop_group == 'sug_values' || empty($this->propertyoptions[$prop_group])) continue;

		// loop through default unit props
		if (!empty($this->preferences['my_props'][$prop_group]['pg_props'])){
			$first = true;
			foreach ($this->preferences['my_props'][$prop_group]['pg_props'] as $prop => $arr){

				if (!isset($this->propertyoptions[$prop_group][$prop]['default_unit'])){
					continue;
				}

				unset($units);
				// user doesn't need to set all padding (for instance) individually
				$factoryUnit = $this->propertyoptions[$prop_group][$prop]['default_unit'];
				$box_model_rel = false;
				$first_in_group = false;
				//$label = $arr['label'];
				$label = $this->propertyoptions[$prop_group][$prop]['label'];

				if (!empty($this->propertyoptions[$prop_group][$prop]['unit_rel'])){
					$box_model_rel = $this->propertyoptions[$prop_group][$prop]['unit_rel'];
				} elseif (!empty($this->propertyoptions[$prop_group][$prop]['rel'])){
					$box_model_rel = $this->propertyoptions[$prop_group][$prop]['rel'];
				}

				if (!empty($this->propertyoptions[$prop_group][$prop]['unit_sub_label'])){
					$first_in_group = $this->propertyoptions[$prop_group][$prop]['unit_sub_label'];
				} elseif (!empty($this->propertyoptions[$prop_group][$prop]['sub_label'])){
					$first_in_group = $this->propertyoptions[$prop_group][$prop]['sub_label'];
				}

				// only output length units
				if ( !isset($arr['default_unit'])
				     or $this->is_non_length_unit($factoryUnit, $prop)
				     or ($box_model_rel and !$first_in_group)
				){
					continue;
				}
				// use group sub label if first box model e.g. padding, margin, border width, border radius
				if ($box_model_rel and $first_in_group){
					$label = $first_in_group; // . esc_html__(' (all)', 'microthemer');
				}
				// we don't need position repeated all the time (but no biggy if non-english)
				$label = str_replace(' (Position)', '', $label);

				// output pg group heading if new group
				if ($first){
					// get the label for the property group (can't necessarily rely on $first_in_group)
					foreach ($this->propertyoptions[$prop_group] as $p => $arr){
						$pg_label = !empty($arr['pg_label']) ? $arr['pg_label'] : '';
						break; // only need first
					}

					$group_key = strtolower(str_replace(' ', '', $pg_label));
					$unit_cats[$group_key] = array(
						'label' => $pg_label,
						'items' => array()
					);

					$first = false;
				}

				$unit_cats[$group_key]['items']['cssu_'.$prop] = array(
					'is_text' => 1,
					'one_line' => 1,
					'prop' => $prop,
					'input_class' => 'custom_css_unit',
					'arrow_class' => 'custom_css_unit',
					'combobox' => 'css_length_units',
					'input_name' => 'tvr_preferences[new_css_units]['.$prop_group.']['.$prop.']',
					'input_value' => $this->preferences['my_props'][$prop_group]['pg_props'][$prop]['default_unit'],
					'label' => $label,
					'explain' => __('Set the default CSS unit for ', 'microthemer') . $label
				);


			}
		}
	}

	// output
	echo $this->preferences_grid($unit_cats, 'css-units-grid');
	?>
</ul>

