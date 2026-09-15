<?php
# Include namespaces
namespace MorkvaMonoGateway;

/**
 * Class Morkva_Mono_Order file
 * */
class Morkva_Mono_Order 
{

    /**
     * @var integer Current order number
     * */
    protected $mrkv_mono_order_id = 0;

    /**
     * @var integer Current order total amount
     * */
    protected $mrkv_mono_amount; 

    /**
     * @var integer Active hold
     * */
    protected $mrkv_mono_hold_active; 

    /**
     * @var integer Currency number
     * */
    protected $mrkv_mono_ccy;

    /**
     * @var string Reference payment
     * */
    protected $mrkv_mono_reference = "";

    /**
     * @var string Payment destination
     * */
    protected $mrkv_mono_destination = "";

    /**
     * @var string Payment destination
     * */
    protected $mrkv_mono_discounts = [];

    /**
     * @param array Order items
     * */
    protected $mrkv_mono_basketOrder = [];

    /**
     * @var string Redirect url
     * */
    protected $mrkv_mono_redirectUrl;

    /**
     * @var string Web hook url
     * */
    protected $mrkv_mono_webHookUrl;

    /**
     * @var string Card Token
     * */
    protected $mrkv_mono_cardToken;

    /**
     * @var string Hold active
     * */
    protected $mrkv_mono_customerEmails;

    /**
     * Set order id
     * @param integer Order number
     * */
    public function mrkv_mono_setId($order_id) 
    {
        # Set data
        $this->mrkv_mono_order_id = $order_id;
    }

    /**
     * Set order amount
     * @param integer Order amount
     * */
    public function mrkv_mono_setAmount($amount) 
    {
        # Set data
        $this->mrkv_mono_amount = $amount;
    }

    /**
     * Set currency code
     * @param integer Currency code
     * */
    public function mrkv_mono_setCurrency($code) 
    {
        # Set data
        $this->mrkv_mono_ccy = $code;
    }

    /**
     * Set payment reference
     * @param string Payment reference
     * */
    public function mrkv_mono_setReference($str) 
    {
        # Set data
        $this->mrkv_mono_reference = $str;
    }

    /**
     * Set payment destination
     * @param string Payment destination
     * */
    public function mrkv_mono_setDestination($str) 
    {
        # Set data
        $this->mrkv_mono_destination = $str;
    }

    /**
     * Set order items
     * @param array Order items
     * */
    public function mrkv_mono_setBasketOrder($basket_info) 
    {
        # Set data
        $this->mrkv_mono_basketOrder = $basket_info;
    }

    /**
     * Set customer emails
     * @param array Customer emails 
     * */
    public function mrkv_mono_setCustomerEmails($customer_emails) 
    {
        # Set data
        $this->mrkv_mono_customerEmails = $customer_emails;
    }

    /**
     * Set order discounts
     * @param array Order discounts
     * */
    public function mrkv_mono_setDiscounts($discounts) 
    {
        # Set data
        $this->mrkv_mono_discounts = $discounts;
    }

    /**
     * Set redirect url
     * @param string Redirect url
     * */
    public function mrkv_mono_setRedirectUrl($url) 
    {
        # Set data
        $this->mrkv_mono_redirectUrl = $url;
    }

    /**
     * Set webhook url
     * @param string Webhook url
     * */
    public function mrkv_mono_setWebHookUrl($url) 
    {
        # Set data
        $this->mrkv_mono_webHookUrl = $url;
    }

    /**
     * Set holds
     * @param string Holds
     * */
    public function mrkv_mono_setHolds($is_hold) 
    {
        # Set data
        $this->mrkv_mono_hold_active = $is_hold ? 'hold' : 'debit';
    }

    /**
     * Set card tokent
     * @param string Card Token
     * */
    public function mrkv_mono_setCardToken($card_token) 
    {
        # Set data
        $this->mrkv_mono_cardToken = $card_token;
    }

    public function mrkv_mono_hold_payment()
    {
        # Get data
        return $this->mrkv_mono_hold_active;
    }

    /**
     * Get order id
     * @return integer Order id
     * */
    public function mrkv_mono_getId(): int
    {
        # Get data
        return $this->mrkv_mono_order_id;
    }

    /**
     * Get order amount
     * @return integer Order amount
     * */
    public function mrkv_mono_getAmount() 
    {
        # Get data
        return $this->mrkv_mono_amount;
    }

    /**
     * Get currency code
     * @return integer currency code
     * */
    public function mrkv_mono_getCurrency(): int
    {
        # Set Iso code
        $iso_code = '';

        # Swtich currency code
        switch($this->mrkv_mono_ccy){
            case 'UAH':
                $iso_code = "980";
            break;
            case 'USD':
                $iso_code = "840";
            break;
            case 'EUR':
                $iso_code = "978";
            break;
            case 'GBP':
                $iso_code = "826";
            break;
            default:
                $iso_code = "980";
        }

        # Get data
        return $iso_code;
    }

    /**
     * Get payment reference
     * @return string Payment reference
     * */
    public function mrkv_mono_getReference(): string
    {
        # Get data
        return $this->mrkv_mono_reference;
    }

    /**
     * Get payment destination
     * @return string Payment destination
     * */
    public function mrkv_mono_getDestination(): string
    {
        # Get data
        return $this->mrkv_mono_destination;
    }

    /**
     * Get order items
     * @return array Order items
     * */
    public function mrkv_mono_getBasketOrder(): array
    {
        # Get data
        return $this->mrkv_mono_basketOrder;
    }

    /**
     * Get customer emails
     * @return array Customer emails
     * */
    public function mrkv_mono_getCustomerEmails(): array
    {
        # Get data
        return $this->mrkv_mono_customerEmails;
    }

    /**
     * Get order discounts
     * @return array Order discounts
     * */
    public function mrkv_mono_getDiscounts(): array
    {
        # Get data
        return $this->mrkv_mono_discounts;
    }

    /**
     * Get redirect url
     * @return string Redirect url
     * */
    public function mrkv_mono_getRedirectUrl() 
    {
        # Get data
        return $this->mrkv_mono_redirectUrl;
    }

    /**
     * Get webhook url
     * @return string Webhook url
     * */
    public function mrkv_mono_getWebHookUrl() 
    {
        # Get data
        return $this->mrkv_mono_webHookUrl;
    }
    
    /**
     * Get card token
     * @return string Card token
     * */
    public function mrkv_mono_getCardToken() 
    {
        # Get data
        return $this->mrkv_mono_cardToken;
    }
}