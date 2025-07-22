<?php
// src/Controller/ProfessionalListController.php
namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfessionalListController extends AbstractController
{
    #[Route('/professionnels', name: 'app_professional_list', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        // Récupération des paramètres de recherche et filtres
        $search = $request->query->get('search', '');
        $city = $request->query->get('city');
        $city = $city ?? ''; 
        $businessName = $request->query->get('business_name', '');
        
        // Construction de la requête avec filtres
        $queryBuilder = $userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%');
        
        // Filtre de recherche générale
        if (!empty($search)) {
            $queryBuilder->andWhere('
                u.firstName LIKE :search 
                OR u.lastName LIKE :search 
                OR u.businessName LIKE :search 
                OR u.presentationDescription LIKE :search
                OR u.businessAddress LIKE :search
            ')
            ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par ville (recherche dans l'adresse)
        if (!empty($city)) {
            $queryBuilder->andWhere('u.businessAddress LIKE :city')
                ->setParameter('city', '%' . $city . '%');
        }
        
        // Filtre par nom d'entreprise
        if (!empty($businessName)) {
            $queryBuilder->andWhere('u.businessName LIKE :businessName')
                ->setParameter('businessName', '%' . $businessName . '%');
        }
        
        // Ordre alphabétique par nom d'entreprise puis nom/prénom
        $queryBuilder->orderBy('u.businessName', 'ASC')
                    ->addOrderBy('u.lastName', 'ASC')
                    ->addOrderBy('u.firstName', 'ASC');
        
        $professionals = $queryBuilder->getQuery()->getResult();
        
        // Récupération des villes uniques pour le filtre
        $cityQueryBuilder = $userRepository->createQueryBuilder('u')
            ->select('u.businessAddress')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->andWhere('u.businessAddress IS NOT NULL')
            ->groupBy('u.businessAddress')
            ->orderBy('u.businessAddress', 'ASC');
        
        $addressResults = $cityQueryBuilder->getQuery()->getResult();
        $cities = [];
        
        // Extraction des villes à partir des adresses
        foreach ($addressResults as $result) {
            $address = $result['businessAddress'];
            $lines = explode("\n", $address);
            $lastLine = trim(end($lines));
            if (preg_match('/\d{5}\s+(.+)/i', $lastLine, $matches)) {
                $extractedCity = trim($matches[1]);
                if (!in_array($extractedCity, $cities)) {
                    $cities[] = $extractedCity;
                }
            }
        }
        sort($cities);
        
        // Récupération des noms d'entreprise uniques pour le filtre
        $businessNames = $userRepository->createQueryBuilder('u')
            ->select('u.businessName')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->andWhere('u.businessName IS NOT NULL')
            ->groupBy('u.businessName')
            ->orderBy('u.businessName', 'ASC')
            ->getQuery()
            ->getResult();
        
        $businessNamesList = array_column($businessNames, 'businessName');
        
        return $this->render('professional_list/index.html.twig', [
            'professionals' => $professionals,
            'cities' => $cities,
            'businessNames' => $businessNamesList,
            'currentSearch' => $search,
            'currentCity' => $city,
            'currentBusinessName' => $businessName,
            'totalResults' => count($professionals),
        ]);
    }
}
