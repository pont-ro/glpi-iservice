<?php
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PluginIserviceTranslationExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('_t', '_t'),
            new TwigFunction('_tn', '_tn'),
        ];
    }
}