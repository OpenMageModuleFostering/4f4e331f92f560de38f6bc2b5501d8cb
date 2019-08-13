<?php

class Send24_Shipping_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = 'send24_shipping';

    public function collectRates(
    Mage_Shipping_Model_Rate_Request $request
    ) {
        $result = Mage::getModel('shipping/rate_result');
        $result->append($this->_getStandardShippingRate());
        
        $expressWeightThreshold = $this->getConfigData('express_weight_threshold');

        $eligibleForExpressDelivery = true;
     
        if ($eligibleForExpressDelivery) {
            $result->append($this->_getExpressShippingRate());
        }
        
        return $result;
    }

    protected function _getStandardShippingRate() {
    }


    public function after_order_placed($observer) {
        $incrementId = $observer->getOrder()->getIncrementId();
        // DK.
        $country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountryId();
        $postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
        $auth = base64_encode($send24_consumer_secret.':'.$send24_consumer_key);
        $select_country = 'Ekspres';

        // get/check Express.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_products");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Basic ".$auth
            ));
        $send24_countries = json_decode(curl_exec($ch));
        curl_close($ch);
        $n = count($send24_countries);
        for ($i = 0; $i < $n; $i++)
        {
            if ($send24_countries[$i]->title == $select_country)
            {   
                $coast = $send24_countries[$i]->price;
                $send24_product_id = $send24_countries[$i]->product_id;               
                $i = $n;
                $is_available = true;
            }else{ 
                $is_available = false;
            }
        }

        if($is_available == true){
            $insurance_price = 0;
            $discount = "false";
            $ship_total = $type = $price_need = '';
            $user_id = $observer->getOrder()->getCustomerId();

            $shipping_data = $observer->getOrder()->getShippingAddress()->getData();
            $billing_data = $observer->getOrder()->getBillingAddress()->getData();

            if($select_country == 'Ekspres'){ $select_country = 'Danmark'; $where_shop_id = 'ekspres'; }
 
            // Create order.
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/create_order");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '
                                            {
                                            "TO_company": "'.$shipping_data['company'].'",
                                            "TO_first_name": "'.$shipping_data['firstname'].'",
                                            "TO_last_name": "'.$shipping_data['lastname'].'",
                                            "TO_phone": "'.$shipping_data['telephone'].'",
                                            "TO_email": "'.$shipping_data['email'].'",
                                            "TO_country": "'.$select_country.'",
                                            "TO_city": "'.$shipping_data['city'].'",
                                            "TO_postcode": "'.$postcode.'",
                                            "Insurance" : "'.$insurance_price.'",
                                            "Weight": "5",
                                            "TO_address": "'.$shipping_data['street'].'",
                                            "WHAT_product_id": "'.$send24_product_id.'",
                                            "WHERE_shop_id": "'.$where_shop_id.'",
                                            "discount": "'.$discount.'",
                                            "type": "'.$type.'",
                                            "need_points": "'.$price_need.'",
                                            "total": "'.$ship_total.'",
                                            "ship_mail": "'.$shipping_data['email'].'",
                                            "bill_mail": "'.$billing_data['email'].'"
                                            }
                                            ');

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Authorization: Basic " .$auth,
                "Content-Type: application/json",
            ));
            $response = curl_exec($ch);
            curl_close($ch);
        }
        

        $response_order = json_decode($response, JSON_FORCE_OBJECT);

        $history = Mage::getModel('sales/order_status_history')
                            ->setStatus($observer->getOrder()->getStatus())
                            ->setComment('<strong>Track parsel </strong><br><a href="'.$response_order['track'].'" target="_blank">'.$response_order['track'].'</a>')
                            ->setEntityName(Mage_Sales_Model_Order::HISTORY_ENTITY_NAME)
                            ->setIsCustomerNotified(false)
                            ->setCreatedAt(date('Y-m-d H:i:s', time() - 60*60*24));

        $observer->getOrder()->addStatusHistory($history);
        $observer->getOrder()->save();
        return true;
    }




    protected function _getExpressShippingRate() {
        $rate = Mage::getModel('shipping/rate_result_method');
        // DK
        $country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountryId();
        $postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();

        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
        $auth = base64_encode($send24_consumer_secret.':'.$send24_consumer_key);
        $select_country = 'Ekspres';

        // get/check Express.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_products");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Basic ".$auth
            ));
        $send24_countries = json_decode(curl_exec($ch));
        curl_close($ch);
        $n = count($send24_countries);
        for ($i = 0; $i < $n; $i++)
        {
            if ($send24_countries[$i]->title == $select_country)
            {   
                $coast = $send24_countries[$i]->price;
                $product_id = $send24_countries[$i]->product_id;               
                $i = $n;
                $is_available = true;
            }else{ 
                $is_available = false;
            }
        }

        if($is_available == true){
            //////////////
            $shipping_address_1 = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getData('street');
            $shipping_postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
            $shipping_city = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCity();
            $shipping_country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry();
            if($shipping_country == 'DK'){
                $shipping_country = 'Denmark';
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_user_id");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Authorization: Basic ".$auth
                ));
            $user_meta = json_decode(curl_exec($ch));

            $billing_address_1 = $user_meta->billing_address_1['0'];
            $billing_postcode = $user_meta->billing_postcode['0'];
            $billing_city = $user_meta->billing_city['0'];
            $billing_country = $user_meta->billing_country['0'];
            if($billing_country == 'DK'){
                $billing_country = 'Denmark';
            }

            $full_billing_address = "$billing_address_1, $billing_postcode $billing_city, $billing_country";
            $full_shipping_address = "$shipping_address_1, $shipping_postcode $shipping_city, $shipping_country";

            // Get billing coordinates.
            $billing_url = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($full_billing_address);
            $billing_latlng = get_object_vars(json_decode(file_get_contents($billing_url)));
            $billing_lat = $billing_latlng['results'][0]->geometry->location->lat;
            $billing_lng = $billing_latlng['results'][0]->geometry->location->lng;

            // Get shipping coordinates.
            $shipping_url = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($full_shipping_address);
            $shipping_latlng = get_object_vars(json_decode(file_get_contents($shipping_url)));
            $shipping_lat = $shipping_latlng['results'][0]->geometry->location->lat;
            $shipping_lng = $shipping_latlng['results'][0]->geometry->location->lng;

            // get_is_driver_area_five_km
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_is_driver_area_five_km");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '
                                            {
                                                "billing_lat": "'.$billing_lat.'",
                                                "billing_lng": "'.$billing_lng.'",
                                                "shipping_lat": "'.$shipping_lat.'",
                                                "shipping_lng": "'.$shipping_lng.'"
                                            }
                                            ');

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Authorization: Basic ".$auth
            ));

            $response = curl_exec($ch);
            $res = json_decode($response);
            if(!empty($res)){
                $rate->setCarrier($this->_code);
                $rate->setCarrierTitle($this->getConfigData('title'));
                $rate->setMethod('express');
                $rate->setMethodTitle('Send24 Sameday(ETA: '.$res->time.') - ');
                $rate->setPrice($coast);
                $rate->setCost(0);
            }
            
            curl_close($ch);
           // print_r($full_billing_address);
            return $rate;
        }
           // die;

    }
     
    public function getAllowedMethods() {
        return array(
            'express' => 'Send24 Sameday Solution',
        );
    }

}
