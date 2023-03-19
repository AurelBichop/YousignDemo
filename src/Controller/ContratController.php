<?php

namespace App\Controller;

use Dompdf\Dompdf;
use App\Entity\Contrat;
use App\Form\ContratType;
use App\Repository\ContratRepository;
use App\Service\YousignService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/contrat')]
class ContratController extends AbstractController
{
    #[Route('/', name: 'app_contrat_index', methods: ['GET'])]
    public function index(ContratRepository $contratRepository): Response
    {
        return $this->render('contrat/index.html.twig', [
            'contrats' => $contratRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_contrat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ContratRepository $contratRepository): Response
    {
        $contrat = new Contrat();
        $form = $this->createForm(ContratType::class, $contrat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contratRepository->save($contrat, true);

            return $this->redirectToRoute('app_contrat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contrat/new.html.twig', [
            'contrat' => $contrat,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_contrat_show', methods: ['GET'])]
    public function show(Contrat $contrat): Response
    {
        return $this->render('contrat/show.html.twig', [
            'contrat' => $contrat,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_contrat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Contrat $contrat, ContratRepository $contratRepository): Response
    {
        $form = $this->createForm(ContratType::class, $contrat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contratRepository->save($contrat, true);

            return $this->redirectToRoute('app_contrat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('contrat/edit.html.twig', [
            'contrat' => $contrat,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_contrat_delete', methods: ['POST'])]
    public function delete(Request $request, Contrat $contrat, ContratRepository $contratRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$contrat->getId(), $request->request->get('_token'))) {
            $contratRepository->remove($contrat, true);
        }

        return $this->redirectToRoute('app_contrat_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/pdf', name: 'app_contrat_pdf', methods: ['GET'])]
    public function pdf(Request $request, Contrat $contrat, ContratRepository $contratRepository): Response
    {
        $dompdf = new Dompdf();

        $html = $this->renderView('contrat/pdf.html.twig',[
            'contrat'=>$contrat
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();

        $output = $dompdf->output();
        $filename = 'contrat_'.$contrat->getId().'.pdf';
        $file = $this->getParameter('kernel.project_dir').'/public/'.$filename;
        
        $contrat->setPdfSansSignature($filename);
        $contratRepository->save($contrat,true);

        file_put_contents($file, $output);
        
        return $this->redirectToRoute('app_contrat_show', ['id'=> $contrat->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/signature', name: 'app_contrat_signature', methods: ['GET'])]
    public function signature(Contrat $contrat, ContratRepository $contratRepository,YousignService $yousignService): Response
    {
        // 01 création de la demande de signature
        $yousignSignatureRequest = $yousignService->signatureRequest();
        $contrat->setSignatureId($yousignSignatureRequest['id']);
        $contratRepository->save($contrat, true);

        // 02 Upload du document
        $uploadDocument = $yousignService->uploadDocument($contrat->getSignatureId(), $contrat->getPdfSansSignature());
        $contrat->setDocumentId($uploadDocument['id']);
        $contratRepository->save($contrat, true);

        // 03 Ajout des Signataires
        $signerId = $yousignService->addSigner(
            $contrat->getSignatureId(),
            $contrat->getDocumentId(),
            $contrat->getMail(),
            $contrat->getPrenom(),
            $contrat->getNom()
        );
        $contrat->setSignerId($signerId['id']);
        $contratRepository->save($contrat,true);

        // 04 Envoie de la demande de signature
        $yousignService->activateSignatureRequest($contrat->getSignatureId());

        return $this->redirectToRoute('app_contrat_show', ['id'=>$contrat->getId()]);
    }
}
