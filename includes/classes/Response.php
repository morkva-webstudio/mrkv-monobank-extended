<?php
# Include namespaces
namespace MorkvaMonoGateway;

/**
 * Class Morkva_Mono_Response file
 * */
class Morkva_Mono_Response 
{
    /**
     * @var integer Order id
     * */
    protected $mrkv_mono_order_id;

    /**
     * @var string Status
     * */
    protected $mrkv_mono_status;

    /**
     * @var integer Invoice id
     * */
    protected $mrkv_mono_invoiceId;

    /**
     * Constructor for the gateway
     * */
    public function __construct($data)
    {
        # Set all data
        $this->mrkv_mono_order_id = $data['reference'];
        $this->mrkv_mono_status = $data['status'];
        $this->mrkv_mono_invoiceId = $data['invoiceId'];
    }

    /**
     * Get order id
     * @return integer Order id
     * */
    public function mrkv_mono_getOrderId() 
    {
        # Get data
        return $this->mrkv_mono_order_id;
    }

    /**
     * Get status
     * @return string Status
     * */
    public function mrkv_mono_getStatus() 
    {
        # Get data
        return $this->mrkv_mono_status;
    }

    /**
     * Get invoice id
     * @return integer Invoice id
     * */
    public function mrkv_mono_getInvoiceId() 
    {
        # Get data
        return $this->mrkv_mono_invoiceId;
    }

    /**
     * Get Status compare
     * @return boolean Status compare
     * */
    public function mrkv_mono_isComplete () 
    {
        # Get data
        return $this->mrkv_mono_status == "success";
    }
}