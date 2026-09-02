<?php

namespace App\Enum;

enum AttributeCategory: string
{
    case Personal = 'personal';
    case Contact = 'contact';
    case Education = 'education';
    case Experience = 'experience';
    case Skills = 'skills';
    case Languages = 'languages';
    case Other = 'other';
}