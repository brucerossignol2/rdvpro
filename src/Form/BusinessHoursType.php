<?php
// src/Form/BusinessHoursType.php
namespace App\Form;

use App\Entity\BusinessHours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BusinessHoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, [
                'label' => 'Jour de la semaine',
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                    'Samedi' => 6,
                    'Dimanche' => 7,
                ],
                'attr' => ['class' => 'form-select'],
                'disabled' => true,
            ])
            ->add('isOpen', CheckboxType::class, [
                'label' => 'Ouvert ce jour',
                'required' => false,
                'help' => 'Décochez si vous êtes fermé toute la journée.',
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début (1ère plage)',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin (1ère plage)',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('startTime2', TimeType::class, [
                'label' => 'Heure de début (2ème plage)',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('endTime2', TimeType::class, [
                'label' => 'Heure de fin (2ème plage)',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BusinessHours::class,
            'constraints' => [
                new Callback([$this, 'validateBusinessHours']),
            ],
        ]);
    }

    public function validateBusinessHours(BusinessHours $businessHours, ExecutionContextInterface $context): void
    {
        if ($businessHours->isIsOpen()) {
            // Validation for first time slot
            if (null === $businessHours->getStartTime()) {
                $context->buildViolation('L\'heure de début (1ère plage) est obligatoire si le jour est ouvert.')
                    ->atPath('startTime')
                    ->addViolation();
            }
            if (null === $businessHours->getEndTime()) {
                $context->buildViolation('L\'heure de fin (1ère plage) est obligatoire si le jour est ouvert.')
                    ->atPath('endTime')
                    ->addViolation();
            }
            if ($businessHours->getStartTime() && $businessHours->getEndTime() && $businessHours->getStartTime() >= $businessHours->getEndTime()) {
                $context->buildViolation('L\'heure de fin (1ère plage) doit être après l\'heure de début (1ère plage).')
                    ->atPath('endTime')
                    ->addViolation();
            }

            // Validation for second time slot if provided
            if ($businessHours->getStartTime2() || $businessHours->getEndTime2()) {
                if (null === $businessHours->getStartTime2()) {
                    $context->buildViolation('L\'heure de début (2ème plage) est obligatoire si une 2ème plage est définie.')
                        ->atPath('startTime2')
                        ->addViolation();
                }
                if (null === $businessHours->getEndTime2()) {
                    $context->buildViolation('L\'heure de fin (2ème plage) est obligatoire si une 2ème plage est définie.')
                        ->atPath('endTime2')
                        ->addViolation();
                }
                if ($businessHours->getStartTime2() && $businessHours->getEndTime2() && $businessHours->getStartTime2() <= $businessHours->getEndTime()) {
                    $context->buildViolation('L\'heure de début (2ème plage) doit être après l\'heure de fin (1ère plage).')
                        ->atPath('startTime2')
                        ->addViolation();
                }
                if ($businessHours->getStartTime2() && $businessHours->getEndTime2() && $businessHours->getStartTime2() >= $businessHours->getEndTime2()) {
                    $context->buildViolation('L\'heure de fin (2ème plage) doit être après l\'heure de début (2ème plage).')
                        ->atPath('endTime2')
                        ->addViolation();
                }
            }
        } else {
            // If not open, ensure all time fields are null
            if (null !== $businessHours->getStartTime() || null !== $businessHours->getEndTime() ||
                null !== $businessHours->getStartTime2() || null !== $businessHours->getEndTime2()) {
                $context->buildViolation('Les heures de début et de fin doivent être vides si le jour est fermé.')
                    ->atPath('isOpen') // Pointing to isOpen as the root cause for clearing times
                    ->addViolation();
            }
        }
    }
}
