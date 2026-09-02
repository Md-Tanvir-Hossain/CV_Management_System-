<?php

namespace App\Enum;

enum AccessRuleOperator: string
{
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Equals = 'equals';
    case Contains = 'contains';
    case IsChecked = 'is_checked';
}