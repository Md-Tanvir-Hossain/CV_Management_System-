<?php

namespace App\Form;

use App\Entity\Attribute;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType as AttributeDataType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AttributeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('category', ChoiceType::class, ['choices' => $this->enumChoices(AttributeCategory::cases())])
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => '', 'attr' => ['rows' => 3]])
            ->add('type', ChoiceType::class, ['choices' => $this->enumChoices(AttributeDataType::cases())])
            ->add('optionsText', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Dropdown options (one per line)',
                'help' => 'Used only for one-of-many dropdown attributes.',
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Attribute::class]);
    }

    /** @param list<\BackedEnum> $cases */
    private function enumChoices(array $cases): array
    {
        $choices = [];
        foreach ($cases as $case) {
            $choices[ucwords(str_replace('_', ' ', $case->value))] = $case;
        }

        return $choices;
    }
}