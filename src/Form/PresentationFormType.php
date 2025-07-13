<?php
// src/Form/PresentationFormType.php
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length; // Assurez-vous que cette contrainte est bien importée

class PresentationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('presentationDescription', TextareaType::class, [
                'label' => 'Description de votre entreprise / présentation personnelle',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Décrivez votre activité, votre philosophie, ce qui vous rend unique...',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Length([
                        'max' => 2000,
                        'maxMessage' => 'Votre description ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('presentationImageFile', FileType::class, [
                'label' => 'Photo représentative (optionnel)',
                'mapped' => false, // Ce champ n'est pas directement mappé à une propriété de l'entité
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2048k', // 2MB max
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG, WEBP) et de taille inférieure à 2 Mo.',
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('presentationLogoFile', FileType::class, [ // Nouveau champ pour le logo
                'label' => 'Logo de la société (optionnel)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2048k', // 2MB max, ajustez si nécessaire
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un logo valide (JPG, PNG, WEBP) et de taille inférieure à 2 Mo.',
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
