<?php

namespace App;

require 'vendor/autoload.php';

use DateTime;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

class Scrape
{
    private string $url = 'https://www.magpiehq.com/developer-challenge/smartphones/';
    private array $products = [];
    private array $productTitleColourKeys = [];

    public function run(): void
    {
        $this->fetchProductsFromPage($this->getUrl());

        file_put_contents('output.json', json_encode($this->products));
    }

    private function fetchProductsFromPage(string $url): void
    {
        $document = ScrapeHelper::fetchDocument($url);
        $this->products = array_merge($this->products, $this->createProductsFromDocument($document));
        $nextPageUrl = $this->getNextPageUrl($document);
        if (is_string($nextPageUrl)) {
            $this->fetchProductsFromPage($nextPageUrl);
        }
    }

    private function createProductsFromDocument(Crawler $node): array
    {
        $products = [];

        $this->getProducts($node)->each(function (Crawler $node) use (& $products) {
            $capacity = $this->getCapacity($node);
            $title = $this->getTitle($node, $capacity);

            if (is_null($title)) {
                return;
            }

            $price = $this->getPrice($node);
            $imageUrl = $this->getImageUrl($node);
            $capacityMB = $this->getCapacityMB($capacity);
            [$availability, $shipping] = $this->getAvailabilityAndShipping($node);
            $availabilityText = $this->getAvailabilityText($availability);
            $isAvailable = $this->getIsAvailable($availabilityText ?? '');
            [$shippingText, $shippingDate] = $this->getShippingDetails($shipping ?? '');

            $colours = $this->getColours($node);
            foreach ($colours as $colour) {
                $isValid = $this->pushTitleColourKey($title, $colour);
                if ($isValid) {
                    array_push($products, new Product(
                        $title,
                        $price,
                        $imageUrl,
                        $capacityMB,
                        $colour,
                        $availabilityText,
                        $isAvailable,
                        $shippingText,
                        $shippingDate
                    ));
                }
            }
        });

        return $products;
    }

    private function getProducts(Crawler $node): Crawler
    {
        return $node->filter('body #products .product');
    }

    private function getCapacity(Crawler $node)
    {
        return $node->filter('.product-capacity')->text();
    }

    private function getAvailabilityAndShipping(Crawler $node): array
    {
        $array = $node->filter('.my-4.text-sm.block.text-center')
            ->each(function (Crawler $node) {
                return $node->text();
            });

        $availability = $array[0] ?? null;
        $shipping = $array[1] ?? null;

        return [$availability, $shipping];
    }

    private function getTitle(Crawler $node, $capacity)
    {
        $array = array_filter([
            $node->filter('.product-name')->text(),
            $capacity
        ]);

        if (count($array) === 0) {
            return;
        }

        return implode(' ', $array);
    }

    private function getPrice(Crawler $node)
    {
        $priceRegex = '/£(?<price>(\d*[.])?\d+)/';
        $text = $node->filter('.my-8.block.text-center.text-lg')->text();
        preg_match($priceRegex, $text, $matches);
        if (array_key_exists('price', $matches)) {
            return (float) $matches['price'];
        }
    }

    private function getImageUrl(Crawler $node)
    {
        $src = $node->filter('img')->attr('src');

        if (!is_string($src)) {
            return;
        }

        return UriResolver::resolve($src, $this->getUrl());
    }

    private function getCapacityMB($capacityText)
    {
        if (is_null($capacityText)) {
            return;
        }

        $capacityRegex = '/(?<capacity>\d*)(\s?)(?<unit>\D*)/';
        preg_match($capacityRegex, $capacityText, $matches);
        return CapacityHelper::getMB(
            $matches['capacity'] ?? null,
            $matches['unit'] ?? null
        );
    }

    private function getColours(Crawler $node)
    {
        return $node->filter('span[data-colour]')->extract(['data-colour']);
    }

    private function getAvailabilityText(string $text)
    {
        $priceRegex = '/Availability:\s(?<availability>.*)/';
        preg_match($priceRegex, $text, $matches);
        if (array_key_exists('availability', $matches)) {
            return $matches['availability'];
        }
    }

    private function getIsAvailable(string $availabilityText)
    {
        $isAvailableRegex = '/(?<isAvailable>In\sStock)/';
        preg_match($isAvailableRegex, $availabilityText, $matches);
        return array_key_exists('isAvailable', $matches);
    }

    private function getShippingDetails(string $text): array
    {
        $shippingDateRegex = '/((\bDelivery\sby\s\b)|(\bDelivery\sfrom\s\b)|(\bDelivers\s\b))(?<date>.*)/';

        if (in_array($text, [
            'Unavailable for delivery',
            'Free Delivery',
            'Free Shipping'
        ])) {
            return [$text, null];
        }

        preg_match($shippingDateRegex, $text, $matches);
        if (array_key_exists('date', $matches)) {
            return [
                $text,
                $this->formatShippingDate($matches['date'])
            ];
        }

        return [null, null];
    }

    private function formatShippingDate(string $text)
    {
        $dateFormats = [
            'Y-m-d',
            'd M Y',
            'l dS M Y'
        ];

        foreach ($dateFormats as $dateFormat) {
            $date = DateTime::createFromFormat($dateFormat, $text);
            if ($date) {
                return date_format($date, 'Y-m-d');
            }
        }
    }

    private function pushTitleColourKey(string $title, string $colour): bool
    {
        $titleColourKey = $title.'-'.$colour;

        if (in_array($titleColourKey, $this->productTitleColourKeys)) {
            return false;
        }

        array_push($this->productTitleColourKeys, $titleColourKey);
        return true;
    }

    private function getNextPageUrl(Crawler $node)
    {
        $activeNode = $node->filter('#pages div a.active')->eq(0);
        $nextAll = $activeNode->nextAll();

        if ($nextAll->count() === 0) {
            return;
        }

        $attributes = $nextAll->eq(0)->extract(['href']);
        $href = $attributes[0];
        return UriResolver::resolve($href, $this->getUrl());
    }

    private function getUrl(): string
    {
        return $this->url;
    }
}

$scrape = new Scrape();
$scrape->run();
