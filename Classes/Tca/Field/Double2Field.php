<?php

declare(strict_types=1);

namespace Typo3Api\Tca\Field;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Typo3Api\Builder\Context\TcaBuilderContext;

class Double2Field extends AbstractField
{
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'min' => 0.0,
            'max' => 1_000_000.0, // default up to a million
            'size' => function (Options $options) {
                /**
                 * @phpstan-ignore-next-line
                 */
                $preDecimalSize = max(strlen((string)(int)$options['min']), strlen((string)(int)$options['max']));
                return $preDecimalSize + 3; // point + 2 digits after the point
            },
            'default' => fn(Options $options) => // try to get default as close to 0 as possible
max($options['min'], min($options['max'], 0.0)),

            'dbType' => function (Options $options) {
                $decimals = 2; // hardcoded because typo3 only offers double2 validation
                $default = number_format($options['default'], $decimals, '.', '');
                /**
                 * @phpstan-ignore-next-line
                 */
                $digits = max(strlen((string)abs((int)$options['min'])), strlen((string)abs((int)$options['max']))) + $decimals;

                if ($options['min'] < 0.0) {
                    return "NUMERIC($digits, $decimals) DEFAULT '$default' NOT NULL";
                }

                return "NUMERIC($digits, $decimals) UNSIGNED DEFAULT '$default' NOT NULL";
            },
            // a double field is most of the time not required to be localized
            'localize' => false,
        ]);

        $resolver->setAllowedTypes('min', ['int', 'double']);
        $resolver->setAllowedTypes('max', ['int', 'double']);
        $resolver->setAllowedTypes('size', 'int');
        $resolver->setAllowedTypes('default', ['int', 'double']);
    }

    public function getFieldTcaConfig(TcaBuilderContext $tcaBuilder): array
    {
        return [
            'type' => 'number',
            'format' => 'decimal',
            'size' => (int)($this->getOption('size') / 2), // adjust the size to fit the character count better
            'default' => $this->getOption('default'),
            'range' => [
                'lower' => $this->getOption('min'),
                'upper' => $this->getOption('max')
            ],
        ];
    }
}
