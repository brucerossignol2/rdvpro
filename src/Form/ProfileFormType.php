<?php
// src/Form/ProfileFormType.php
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => ['autocomplete' => 'email', 'class' => 'form-control'],
                'label' => 'Adresse Email de connexion',
                'help' => 'Ceci est votre identifiant de connexion.',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer une adresse email',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre email doit contenir au moins {{ limit }} caractères',
                        'max' => 180,
                    ]),
                ],
            ])
            ->add('firstName', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer votre prénom.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer votre nom.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('businessName', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Nom de votre entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer le nom de votre entreprise.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('businessAddress', TextareaType::class, [
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'label' => 'Adresse de votre entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer l\'adresse de votre entreprise.']),
                    new Length(['min' => 10, 'max' => 255]),
                ],
            ])
            ->add('businessPhone', TelType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Téléphone de l\'entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer le numéro de téléphone de votre entreprise.']),
                    new Regex([
                        'pattern' => '/^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:[\s.-]?\d{2}){4}$/',
                        'message' => 'Veuillez entrer un numéro de téléphone valide (format français).',
                    ]),
                ],
            ])
            ->add('businessEmail', EmailType::class, [
                'attr' => ['autocomplete' => 'email', 'class' => 'form-control'],
                'label' => 'Email de contact de l\'entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un email de contact pour l\'entreprise.']),
                    new Length(['min' => 6, 'max' => 100]),
                ],
            ])
            ->add('bookingLink', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Lien de réservation (URL)',
                'required' => false,
                'help' => 'Lien que vos clients utiliseront pour prendre rendez-vous.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
