<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\ChapterImage;
use App\Entity\Product;
use App\Entity\User;
use App\Form\AddToCartType;
use App\Form\ProductType;
use App\Manager\CartManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use function Symfony\Component\String\u;
use Doctrine\Common\Collections\ArrayCollection;
use App\Repository\ProductRepository;

class ProductController extends AbstractController
{
    #[Route('/', name: 'product_list')]
    public function listAction(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('product/index.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/product', name:'product')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $category = new Category();
        $category->setName('Computer Peripherals');


        $product = new Product();
        $product->setName('Keyboard');
        $product->setPrice(19.99);
        $product->setQuantity(2);
        $product->setDate(new DateTime(0-2-0));
        $product->setDescription('Ergonomic and stylish!');

        // relates this product to the category
        $product->setCategory($category);

        $entityManager = $doctrine->getManager();
        $entityManager->persist($category);
        $entityManager->persist($product);
        $entityManager->flush();

        return new Response(
            'Saved new product with id: ' . $product->getId()
            . ' and new category with id: ' . $category->getId()
        );
    }

    #[Route('/insertUser', name:'product')]
    public function insertAction(ManagerRegistry $doctrine): Response
    {
        $user= new User();

        $user->setEmail('abc@gmail.com');
        $user->setPassword("123@abc");
        // relates this product to the category

        $entityManager = $doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->persist($user);
        $entityManager->flush();

        return new Response(
            'Saved new product with id: '.$user->getId()
            .' and new category with id: '.$user->getId()
        );
    }

    #[Route('/product/details/{id}', name: 'product_details')]
    public function detailsAction(ManagerRegistry $doctrine, $id, Request $request, CartManager $cartManager, ProductRepository $productRepository)
    {
        $entityManager = $doctrine->getManager();
        $products = $entityManager->getRepository(Product::class)->find($id);
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $latestProducts = $productRepository->findLatestProducts($products->getCategory());
        $cart = $cartManager->getCurrentCart();

        $form = $this->createForm(AddToCartType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $item = $form->getData();
            $item->setProduct($products);

            $cart = $cartManager->getCurrentCart();
            $cart
                ->addItem($item)
                ->setUpdatedAt(new \DateTime());

            $cartManager->save($cart);

            return $this->redirectToRoute('product_details', ['id' => $products->getId()]);
        }

        return $this->render('product/details.html.twig',[
            'products' => $products,
            'categories' => $categories,
            'cart' => $cart,
            'form' => $form->createView(),
            'latestProducts' => $latestProducts
        ]);
    }

    #[Route('/admin/product/delete/{id}', name: 'product_delete')]
    public function deleteAction(ManagerRegistry $doctrine,$id)
    {
        $em = $doctrine->getManager();
        $product = $em->getRepository(Product::class)->find($id);
        $em->remove($product);
        $em->flush();

        $this->addFlash(
            'error',
            'Product deleted'
        );
        return $this->redirectToRoute('product_list');
    }



    #[Route('/admin/product/create', name: 'product_create')]
    public function createAction(ManagerRegistry $doctrine,Request $request, SluggerInterface $slugger, CartManager $cartManager, EntityManagerInterface $entityManager)
    {
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $cart = $cartManager->getCurrentCart();

        $form->handleRequest($request);

//        if ($form->isSubmitted() && $form->isValid()) {
//            // upload file
//            $productImage = $form->get('productImage')->getData();
//            if ($productImage) {
//                $originalFilename = pathinfo($productImage->getClientOriginalName(), PATHINFO_FILENAME);
//                // this is needed to safely include the file name as part of the URL
//                $safeFilename = $slugger->slug($originalFilename);
//                $newFilename = $safeFilename . '-' . uniqid() . '.' . $productImage->guessExtension();
//
//                // Move the file to the directory where brochures are stored
//                try {
//                    $productImage->move(
//                        $this->getParameter('productImages_directory'),
//                        $newFilename
//                    );
//                } catch (FileException $e) {
//                    $this->addFlash(
//                        'error',
//                        'Cannot upload'
//                    );// ... handle exception if something happens during file upload
//                }
//                $product->setProductImage($newFilename);
//            }else{
//                $this->addFlash(
//                    'error',
//                    'Cannot upload'
//                );// ... handle exception if something happens during file upload
//            }
//            $em = $doctrine->getManager();
//            $em->persist($product);
//            $em->flush();
//
//            $this->addFlash(
//                'notice',
//                'Product Added'
//            );
//            return $this->redirectToRoute('product_list');
//        }

        if ($form->isSubmitted() && $form->isValid()) {
            $productImages_directory = $this->getParameter('productImages_directory');
            $files = $request->files->get('product')['productImage'];

            // Lưu thông tin chapter vào database trước để có chapter_id cho các chapter image
            $entityManager->persist($product);
            $entityManager->flush();

            foreach ($files as $file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $filename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                $file->move($productImages_directory, $filename);

                // Tạo mới đối tượng ChapterImage và lưu thông tin vào database
                $productImage = new ChapterImage();
                $productImage->setImagePath($filename);
                $productImage->setChapter($product);
                $entityManager->persist($productImage);
            }

            // Lưu tất cả các đối tượng ChapterImage vào database
            $entityManager->flush();

            // Chuyển hướng sau khi thêm chapter thành công
            return $this->redirectToRoute('product_list');
        }

        return $this->renderForm('product/create.html.twig', ['form' => $form, 'categories'=>$categories, 'cart' => $cart]);
    }

    #[Route('/product/productByCat/{id}', name: 'productByCat')]
    public function productByCatAction(ManagerRegistry $doctrine, $id, CartManager $cartManager):Response
    {
        $category = $doctrine->getRepository(Category::class)->find($id);
        $products = $category->getProducts();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        return $this->render('category/details.html.twig', ['category' => $category, 'products' => $products,
            'categories' => $categories, 'cart' => $cart,]);
    }

    #[Route('/admin/product/edit/{id}', name: 'product_edit')]
    public function editAction(ManagerRegistry $doctrine, int $id,Request $request,SluggerInterface $slugger, CartManager $cartManager): Response{
        $entityManager = $doctrine->getManager();
        $product = $entityManager->getRepository(Product::class)->find($id);
        $form = $this->createForm(ProductType::class, @$product);
        $form->handleRequest($request);
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        if ($form->isSubmitted() && $form->isValid()) {
            //upload file
            $productImage = $form->get('productImage')->getData();
            if ($productImage) {
                $originalFilename = pathinfo($productImage->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $productImage->guessExtension();
                // Move the file to the directory where brochures are stored
                try {
                    $productImage->move(
                        $this->getParameter('productImages_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash(
                        'error',
                        'Cannot upload'
                    );// ... handle exception if something happens during file upload
                }
                $product->setProductImage($newFilename);
            }else{
                $this->addFlash(
                    'error',
                    'Cannot upload'
                );// ... handle exception if something happens during file upload
            }

            $em = $doctrine->getManager();
            $em->persist($product);
            $em->flush();
            return $this->redirectToRoute('product_list', [
                'id' => $product->getId()
            ]);

        }
        return $this->renderForm('product/edit.html.twig', ['form' => $form, 'categories' => $categories, 'cart' => $cart]);
    }


    /**
     * @Route("/search", name="product_search")
     */
    public function search(Request $request,ManagerRegistry $doctrine, CartManager $cartManager)
    {
        $keyword = $request->query->get('search');

        // Truy vấn cơ sở dữ liệu để tìm các sản phẩm phù hợp với từ khóa tìm kiếm (name hoặc description)
        $em = $this->entityManager;
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();
        $products = $em->getRepository(Product::class)->createQueryBuilder('p')
            ->where('p.name LIKE :keyword OR p.description LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->getQuery()
            ->getResult();
        return $this->render('product/search_results.html.twig', [
            'products' => $products,
            'keyword' => $keyword,
            'categories' => $categories,
            'cart' => $cart,
        ]);
    }
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    #[Route('/about-us', name: 'about-us')]
    public function Introduce(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/about-us.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/cac-chung-loai-figure', name: 'cac-chung-loai-figure')]
    public function cacchungloaifigure(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/cac-chung-loai-figure.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/cach-phan-biet-figure-nhat-chinh-hang-hang-nhai-tu-trung-quoc', name: 'cach-phan-biet-figure-nhat-chinh-hang-hang-nhai-tu-trung-quoc')]
    public function cachphanbietfigurenhatchinhhanghangnhaitutrungquoc(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/cach-phan-biet-figure-nhat-chinh-hang-hang-nhai-tu-trung-quoc.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/hoi-dap', name: 'hoi-dap')]
    public function hoidap(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/hoi-dap.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/huong-dan-dat-hang-thanh-toan-tai-japan-figure', name: 'huong-dan-dat-hang-thanh-toan-tai-japan-figure')]
    public function huongdandathangthanhtoantaijapanfigure(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/huong-dan-dat-hang-thanh-toan-tai-japan-figure.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/lien-he', name: 'lien-he')]
    public function lienhe(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/lien-he.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/so-tai-khoan', name: 'so-tai-khoan')]
    public function sotaikhoan(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/so-tai-khoan.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/theo-doi-lich-phat-hanh-japan-figure-tu-chinh-hang', name: 'theo-doi-lich-phat-hanh-japan-figure-tu-chinh-hang')]
    public function theodoilichphathanhjapanfiguretuchinhhang(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/theo-doi-lich-phat-hanh-japan-figure-tu-chinh-hang.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/vi-sao-nen-dat-mua-figure-som-tai-japanfigure-vn', name: 'vi-sao-nen-dat-mua-figure-som-tai-japanfigure-vn')]
    public function visaonendatmuafiguresomtaijapanfigurevn(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/vi-sao-nen-dat-mua-figure-som-tai-japanfigure-vn.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

    #[Route('/kakeibo-phuong-phap-tiet-kiem-chi-tieu-hop-ly', name: 'kakeibo-phuong-phap-tiet-kiem-chi-tieu-hop-ly')]
    public function kakeibophuongphaptietkiemchitieuhoply(ManagerRegistry $doctrine,Request $request, CartManager $cartManager): Response
    {
        $products = $doctrine->getRepository(Product::class)->findAll();
        $categories = $doctrine->getRepository(Category::class)->findAll();
        $cart = $cartManager->getCurrentCart();

        $currentPosition = 0;

        return $this->render('news/kakeibo-phuong-phap-tiet-kiem-chi-tieu-hop-ly.html.twig', [
            'products' => $products, 'categories'=>$categories, 'cart' => $cart,
            'currentPosition' => $currentPosition
        ]);
    }

}
