<?php

class Send24_Shipping_Block_Pickup extends Mage_Checkout_Block_Onepage_Shipping_Method_Available
{
	public function __construct(){
		$this->setTemplate('pickup/send24_pickup.phtml');		
	}
}