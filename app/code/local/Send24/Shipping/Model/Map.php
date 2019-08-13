<?php

class Send24_Shipping_Model_Map 
{    
    public function toOptionArray()
    {
        return array(
          array(
            'value' => 'map',
            'label' => 'Map'
          ),
          array(
            'value' => 'dropdown',
            'label' => 'Dropdown'
          ),
        );
    }


}
