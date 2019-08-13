<?php

class Send24_Shipping_Model_Distributors
{    
    public function toOptionArray()
    {     
        return array(
            array(
              'value' => 'PostDanmark',
              'label' => 'PostDanmark'
            ),
            array(
              'value' => 'GLS',
              'label' => 'GLS'
            ),
            array(
              'value' => 'UPS',
              'label' => 'UPS'
            ),
            array(
              'value' => 'DHL',
              'label' => 'DHL'
            ),
            array(
              'value' => 'TNT',
              'label' => 'TNT'
            ),
            array(
              'value' => 'Bring',
              'label' => 'Bring'
            ),
          );
    }
}
