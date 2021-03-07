<?php

namespace App;

use JsonSerializable;

class Product implements JsonSerializable
{
    private $title;
    private $price;
    private $imageUrl;
    private $capacityMB;
    private $colour;
    private $availabilityText;
    private $isAvailable;
    private $shippingText;
    private $shippingDate;

    public function __construct(
        $title,
        $price,
        $imageUrl,
        $capacityMB,
        $colour,
        $availabilityText,
        $isAvailable,
        $shippingText,
        $shippingDate
    ) {
        $this->title = $title;
        $this->price = $price;
        $this->imageUrl = $imageUrl;
        $this->capacityMB = $capacityMB;
        $this->colour = $colour;
        $this->availabilityText = $availabilityText;
        $this->isAvailable = $isAvailable;
        $this->shippingText = $shippingText;
        $this->shippingDate = $shippingDate;
    }

    public function jsonSerialize(): array
    {
        return [
            'title' => $this->getTitle(),
            'price' => $this->getPrice(),
            'imageUrl' => $this->getImageUrl(),
            'capacityMB' => $this->getCapacityMB(),
            'colour' => $this->getColour(),
            'availabilityText' => $this->getAvailabilityText(),
            'isAvailable' => $this->getIsAvailable(),
            'shippingText' => $this->getShippingText(),
            'shippingDate' => $this->getShippingDate()
        ];
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    public function getCapacityMB()
    {
        return $this->capacityMB;
    }

    public function getColour()
    {
        return $this->colour;
    }

    public function getAvailabilityText()
    {
        return $this->availabilityText;
    }

    public function getIsAvailable()
    {
        return $this->isAvailable;
    }

    public function getShippingText()
    {
        return $this->shippingText;
    }

    public function getShippingDate()
    {
        return $this->shippingDate;
    }
}
