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

    public function run(): void
    {
        $this->fetchProductsFromPage($this->getUrl());
        $this->products = $this->deDupeProducts($this->products);

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
        return $this->getProducts($node)->each(function (Crawler $node) {
            $contentTexts = $this->getContentTexts($node);
            $capacity = $this->getCapacity($node);
            $availabilityText = $this->getAvailabilityText($contentTexts);
            [$shippingText, $shippingDate] = $this->getShippingDetails($contentTexts);
            return new Product(
                $this->getTitle($node, $capacity),
                $this->getPrice($contentTexts),
                $this->getImageUrl($node),
                $this->getCapacityMB($capacity),
                $this->getColour($node),
                $availabilityText,
                $this->getIsAvailable($availabilityText),
                $shippingText,
                $shippingDate
            );
        });
    }

    private function getProducts(Crawler $node): Crawler
    {
        return $node->filter('body #products .product');
    }

    private function getContentTexts(Crawler $node): array
    {
        $contentTexts = $node->filter('div div div')
            ->each(function (Crawler $node) {
                return $node->text();
            });

        return array_filter($contentTexts);
    }

    private function getCapacity(Crawler $node)
    {
        return $node->filter('.product-capacity')->text();
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

    private function getPrice(array $contentTexts)
    {
        $priceRegex = '/£(?<price>(\d*[.])?\d+)/';
        foreach ($contentTexts as $text) {
            preg_match($priceRegex, $text, $matches);
            if (array_key_exists('price', $matches)) {
                return (float) $matches['price'];
            }
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

    private function getColour(Crawler $node)
    {
        $attributes = $node->filter('span[data-colour]')->extract(['data-colour']);
        return implode('|', $attributes);
    }

    private function getAvailabilityText(array $contentTexts)
    {
        $priceRegex = '/Availability:\s(?<availability>.*)/';
        foreach ($contentTexts as $text) {
            preg_match($priceRegex, $text, $matches);
            if (array_key_exists('availability', $matches)) {
                return $matches['availability'];
            }
        }
    }

    private function getIsAvailable(string $availabilityText)
    {
        $isAvailableRegex = '/(?<isAvailable>In\sStock)/';
        preg_match($isAvailableRegex, $availabilityText, $matches);
        return array_key_exists('isAvailable', $matches);
    }

    private function getShippingDetails(array $contentTexts): array
    {
        $shippingDateRegex = '/((\bDelivery\sby\s\b)|(\bDelivery\sfrom\s\b)|(\bDelivers\s\b))(?<date>.*)/';
        foreach ($contentTexts as $text) {
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
        }
        return [null, null];
    }

    private function formatShippingDate(string $text)
    {
        $dateFormat = 'd M Y';
        $date = DateTime::createFromFormat($dateFormat, $text);

        if (!$date) {
            return;
        }

        return date_format($date, 'Y-m-d');
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

    private function deDupeProducts(array $products): array
    {
        $titles = [];
        return array_reduce(
            $products,
            function ($carry, $product) use (& $titles) {
                $title = $product->getTitle();
                if (!in_array($title, $titles)) {
                    array_push($titles, $title);
                    array_push($carry, $product);
                }
                return $carry;
            },
            []
        );
    }

    private function getUrl(): string
    {
        return $this->url;
    }
}

$scrape = new Scrape();
$scrape->run();
