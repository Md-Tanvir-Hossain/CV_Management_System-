<?php

namespace App\Enum;

enum AttributeType: string
{
    case String = 'string';
    case Text = 'text';
    case Image = 'image';
    case Numeric = 'numeric';
    case Date = 'date';
    case Period = 'period';
    case Boolean = 'boolean';
    case Dropdown = 'dropdown';
}