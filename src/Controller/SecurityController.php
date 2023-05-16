<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SecurityController extends AbstractController
{

    function __construct(private $formLoginAuthenticator)
    {
        
    }

    #[Route('/signup', name: 'signup')]
    public function signup(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, UserAuthenticatorInterface $userAuthenticator, MailerInterface $mailer): Response
    {
        $user = new User();
        $signupForm = $this->createForm(UserType::class, $user);
        $signupForm->handleRequest($request);

        if ($signupForm->isSubmitted() && $signupForm->isValid()) {
            $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);

            $em->persist($user);
            $em->flush();

            $email = new TemplatedEmail();
            $email->to($user->getEmail())
                    ->subject("Bienvenue sur Quori")
                    ->htmlTemplate('@email_templates/welcome.html.twig')
                    ->context([
                        'fullname' => $user->getFullname()
                    ]);
            $mailer->send($email);


            $this->addFlash('success', 'Bienvenue sur quori !');
            return $userAuthenticator->authenticateUser($user, $this->formLoginAuthenticator,$request);


        }

        return $this->render('security/signup.html.twig', ['form' => $signupForm->createView()]);
    }

    #[Route('/signin', name:'signin')]
    public function signin(AuthenticationUtils $authenticationUtils):Response
    {
        if($this->getUser()){
            return $this->redirectToRoute('home');
        }
        $error = $authenticationUtils->getLastAuthenticationError();
        $username = $authenticationUtils->getLastUsername();
        return $this->render('security/signin.html.twig', [
            'error' => $error,
            'username' => $username
        ]);
    }

    #[Route('/logout', name:'logout')]
    public function logout(){}

    #[Route('/reset-password-request', name: 'reset-password-request')]
    public function resetPasswordRequest(Request $request, UserRepository $userRepository, EntityManagerInterface $em, ResetPasswordRepository $resetPasswordRepository, MailerInterface $mailer) {

        $emailForm = $this->createFormBuilder()
                            ->add('email', EmailType::class, [
                                'constraints' => [
                                    new NotBlank([
                                        'message' => 'Veuillez renseigner ce champ.'
                                    ]),
                                    new Email([
                                        'message' => 'Veuillez entrer un email valide.'
                                    ])
                                ]
                            ])
                            ->getForm();
        
        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()){
            $email = $emailForm->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);

            if($user) {
                // On s'assure qu'il n'y ait pas deja une demande de reset
                $oldResetPassword = $resetPasswordRepository->findOneBy(['user' => $user]);
                if($oldResetPassword){
                    $em->remove($oldResetPassword);
                    $em->flush();
                }

                // On fait un random bytes de 40 caracteres et on en garde que 20
                $token = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(40))), 0, 20);

                $resetPassword = new ResetPassword();
                $resetPassword->setUser($user)
                                ->setToken($token)
                                ->setExpiredAt(new DateTimeImmutable('+2 hours'));

                $em->persist($resetPassword);
                $em->flush();

                // on envoie l'email de reinitialisation
                $resetEmail = new TemplatedEmail();
                $resetEmail->to($email)
                            ->subject('Demande de reinitialisation de mot de passe')
                            ->htmlTemplate('@email_templates/reset-password-request.html.twig')
                            ->context([
                                'fullname' => $user->getFullname(),
                                'token' => $token
                            ]);
                $mailer->send($resetEmail);

                $this->addFlash('success', 'Un email vous a ete envoye.');
                return $this->redirectToRoute('signin');
            } else {
                $this->addFlash('error', "Cet email n'existe pas");
            }

        }

        return $this->render('security/reset-password-request.html.twig', ['form' => $emailForm->createView()]);
    }


    #[Route('/reset-password/{token}', name: 'reset-password')]
    public function resetPassword(string $token,Request $request, ResetPasswordRepository $resetPasswordRepository,EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher){

        // verifier que le token est bien dans la base de données 
        $resetPassword = $resetPasswordRepository->findOneBy(['token' => $token]);
        // verifier qu'il n'a pas expiré 
        if(!$resetPassword || $resetPassword->getExpiredAt()< new DateTime('now')){

            if($resetPassword){
                $em->remove($resetPassword);
                $em->flush();
            }
            $this->addFlash('error','votre deamnde a expiré, veuillez la refaire');
            return $this->redirectToRoute('reset-password-request');
        }
        // formulaire pour saisir le nouveau mot de passe 
        $resetPasswordForm = $this->createFormBuilder()
                                    ->add('password', PasswordType::class, [
                                        'label' => "nouveau mot de passe",
                                        'constraints' => [
                                            new Length([
                                                'min' => 6,
                                                'minMessage' => 'Le mot de passe doit faire au moins 6 caractères '
                                            ]),
                                            new NotBlank([
                                                'message' => 'Veuillez saisir ce champs'
                                            ])
                                        ]
                                    ])
                                    ->getForm();
        $resetPasswordForm->handleRequest($request);
       
        if($resetPasswordForm->isSubmitted() && $resetPasswordForm->isValid()){
            // on recupere l'user
            $user = $resetPassword->getUser();
            // on recupere le nouveau mot de passe depuis le formulaire
            $newPassword = $resetPasswordForm->get('password')->getData();
            $hashedNewPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedNewPassword);

            // on supprime la demande de reset password de la bdd

            $em->remove($resetPassword);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a bien ete mis a jour');
            return $this->redirectToRoute('signin');
        }


        return $this->render('security/reset-password-form.html.twig', ['form' => $resetPasswordForm->createView()]);
        
    }
}
