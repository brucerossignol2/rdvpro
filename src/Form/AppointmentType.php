<?php
// src/Form/AppointmentType.php
namespace App\Form;

use App\Entity\Appointment;
use App\Entity\Client;
use App\Entity\Service;
use App\Repository\ClientRepository;
use App\Repository\ServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security; // Import Security component
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AppointmentType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->security->getUser();

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du rendez-vous / Indisponibilité',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire.']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Note public (Visible par le client)',
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'required' => false,
            ])
            ->add('descriptionPrive', TextareaType::class, [
                'label' => 'Note privée (Non visible par le client)',
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'required' => false,
            ])
            ->add('startTime', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'La date et l\'heure de début sont obligatoires.']),
                ],
            ])
            ->add('endTime', DateTimeType::class, [
                'label' => 'Date et heure de fin',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'La date et l\'heure de fin sont obligatoires.']),
                ],
            ])
            ->add('isPersonalUnavailability', CheckboxType::class, [
                'label' => 'Ceci est une indisponibilité personnelle',
                'required' => false,
                'help' => 'Cochez si ce créneau est pour votre usage personnel (ex: pause, formation) et non un rendez-vous client.',
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'fullName',
                'label' => 'Client',
                'attr' => ['class' => 'form-select'],
                'required' => false,
                'placeholder' => 'Sélectionnez un client',
                'choices' => $options['clients'], // <-- Utilise la liste de clients passée en option
            ])
            ->add('services', EntityType::class, [
                'class' => Service::class,
                'query_builder' => function (ServiceRepository $er) use ($professional) {
                    return $er->createQueryBuilder('s')
                        ->where('s.professional = :professional')
                        ->andWhere('s.active = :active')
                        ->setParameter('professional', $professional)
                        ->setParameter('active', true)
                        ->orderBy('s.name', 'ASC');
                },
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Prestations',
                'attr' => ['class' => 'form-select select2'],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
            'clients' => [], // Déclarez l'option 'clients' avec une valeur par défaut
        ]);
        $resolver->setAllowedTypes('clients', 'array');
    }


    public function validateAppointment(Appointment $appointment, ExecutionContextInterface $context): void
    {
        // Validation pour les heures de début et de fin
        if ($appointment->getStartTime() && $appointment->getEndTime()) {
            if ($appointment->getStartTime() >= $appointment->getEndTime()) {
                $context->buildViolation('L\'heure de fin doit être après l\'heure de début.')
                    ->atPath('endTime')
                    ->addViolation();
            }
        }

        // Si ce n'est pas une indisponibilité personnelle, le client et les services sont obligatoires
        if (!$appointment->isIsPersonalUnavailability()) {
            if (null === $appointment->getClient()) {
                $context->buildViolation('Le client est obligatoire pour un rendez-vous.')
                    ->atPath('client')
                    ->addViolation();
            }
            if ($appointment->getServices()->isEmpty()) {
                $context->buildViolation('Au moins une prestation est obligatoire pour un rendez-vous.')
                    ->atPath('services')
                    ->addViolation();
            }

            // Valider la durée totale des services par rapport à la durée du rendez-vous
            $totalServicesDuration = $appointment->getTotalServicesDuration();
            if ($appointment->getStartTime() && $appointment->getEndTime()) {
                $appointmentDuration = $appointment->getEndTime()->getTimestamp() - $appointment->getStartTime()->getTimestamp();
                $appointmentDurationMinutes = $appointmentDuration / 60;

                if ($totalServicesDuration > $appointmentDurationMinutes) {
                    $context->buildViolation(sprintf(
                        'La durée totale des prestations sélectionnées (%d minutes) dépasse la durée du rendez-vous (%d minutes).',
                        $totalServicesDuration,
                        $appointmentDurationMinutes
                    ))
                    ->atPath('services')
                    ->addViolation();
                }
            }
        } else {
            // Si c'est une indisponibilité personnelle, le client et les services doivent être nuls/vides
            if (null !== $appointment->getClient()) {
                $context->buildViolation('Un client ne peut pas être associé à une indisponibilité personnelle.')
                    ->atPath('client')
                    ->addViolation();
            }
            if (!$appointment->getServices()->isEmpty()) {
                $context->buildViolation('Aucune prestation ne peut être associée à une indisponibilité personnelle.')
                    ->atPath('services')
                    ->addViolation();
            }
        }
    }
}