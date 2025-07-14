<?php
// src/Form/ServiceType.php
namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType; // Ajouter cette ligne
use Symfony\Component\Validator\Constraints\File;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Libellé du service',
                'attr' => ['placeholder' => 'Ex: Consultation individuelle, Massage relaxant...'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnel)',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Décrivez votre service...'],
            ])


            ->add('price', NumberType::class, [ 
            'label' => 'Prix',
            'scale' => 2, 
            'attr' => ['placeholder' => 'Ex: 50.00'],
            ])

            ->add('duration', IntegerType::class, [
                'label' => 'Durée (en minutes)',
                'attr' => ['placeholder' => 'Ex: 45'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Service actif (visible pour la réservation)',
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du service (optionnel)',
                'mapped' => false, // Non mappé directement à l'entité
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG, WEBP).',
                    ])
                ],
            ])
            ->add('appointmentType', ChoiceType::class, [ // Changé en ChoiceType
                'label' => 'Type de rendez-vous',
                'choices' => [
                    'À domicile' => 'home_service',
                    'Chez le professionnel' => 'office_service',
                    'Par visioconférence' => 'video_service',
                    'Par téléphone' => 'phone_service',
                ],
                'expanded' => true, // Rendu comme des boutons radio
                'multiple' => false, // Autoriser un seul choix
                // 'placeholder' => 'Sélectionnez un type de service', // Le placeholder n'est pas utilisé avec expanded => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
