<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname',TextType::class,['label' => 'Prénom*','required' => false])
            ->add('lastname',TextType::class,['label'=>'Nom*','required' => false])
            ->add('email',EmailType::class,['label'=>'Email*','required' => false])
            ->add('password', PasswordType::class, ['label'=>'Mot de passe*','required' => false])
            ->add('image',UrlType::class, ['label' => 'Image de profil','required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}