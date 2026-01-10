<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            // Ordering & Checkout
            [
                'question' => 'How do I place an order?',
                'answer' => "Placing an order is simple! Just follow these steps:\n\n1. Browse our products and select the item you want\n2. Customize your design or upload your own artwork\n3. Choose quantity, size, and other options\n4. Add to cart and proceed to checkout\n5. Enter your shipping details and complete payment\n\nYou'll receive an order confirmation email with your order details and tracking information.",
                'category' => 'ordering',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'question' => 'Can I modify my order after placing it?',
                'answer' => "You can modify your order within 2 hours of placing it, as long as production hasn't started. Contact our support team immediately with your order number and the changes you need. After this window, modifications may not be possible as we start processing orders quickly to ensure fast delivery.",
                'category' => 'ordering',
                'sort_order' => 2,
                'is_featured' => false,
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => "We accept a variety of payment methods for your convenience:\n\n• Credit/Debit Cards (Visa, Mastercard, RuPay)\n• UPI (Google Pay, PhonePe, Paytm)\n• Net Banking\n• Wallets (Paytm, Amazon Pay)\n• Cash on Delivery (for select areas)\n\nAll payments are processed securely with industry-standard encryption.",
                'category' => 'payment',
                'sort_order' => 1,
                'is_featured' => true,
            ],

            // Design & Artwork
            [
                'question' => 'What file formats do you accept for custom designs?',
                'answer' => "We accept the following file formats for the best print quality:\n\n• PDF (Recommended - maintains quality)\n• PNG (with transparent background if needed)\n• JPG/JPEG (high resolution, 300 DPI minimum)\n• AI (Adobe Illustrator)\n• PSD (Adobe Photoshop)\n• SVG (for vector graphics)\n\nFor best results, please ensure your images are at least 300 DPI resolution.",
                'category' => 'design',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'question' => 'Do you offer design services?',
                'answer' => "Yes! We offer professional design services for customers who need help creating their artwork. Our experienced designers can help you with:\n\n• Custom logo design\n• Business card layouts\n• Brochure and flyer design\n• Banner design\n• Complete branding packages\n\nContact us for a quote on design services.",
                'category' => 'design',
                'sort_order' => 2,
                'is_featured' => false,
            ],

            // Printing & Quality
            [
                'question' => 'What printing technologies do you use?',
                'answer' => "We use state-of-the-art printing technology to ensure the highest quality:\n\n• Digital Printing - For small quantities and quick turnaround\n• Offset Printing - For large quantities with consistent colors\n• UV Printing - For special finishes and durability\n• Screen Printing - For fabrics and promotional items\n\nOur quality control team inspects every order before shipping.",
                'category' => 'printing',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'question' => 'Can I see a proof before printing?',
                'answer' => "Absolutely! We offer digital proofs for all orders. After you place your order, you'll receive a digital proof within 24 hours via email. You can review and approve it before we start printing. We won't proceed with production until you give us the go-ahead, ensuring your final product matches your expectations.",
                'category' => 'printing',
                'sort_order' => 2,
                'is_featured' => false,
            ],

            // Shipping & Delivery
            [
                'question' => 'How long does delivery take?',
                'answer' => "Delivery times depend on your location and the shipping method selected:\n\n• Standard Delivery: 5-7 business days\n• Express Delivery: 2-3 business days\n• Same Day Delivery: Available in select cities for orders placed before 12 PM\n\nProduction time is typically 1-2 business days and is added to shipping time. You'll receive tracking information once your order ships.",
                'category' => 'shipping',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'question' => 'Do you deliver across India?',
                'answer' => "Yes, we deliver to all locations across India! We partner with trusted courier services to ensure safe and timely delivery. For remote areas, delivery may take 1-2 additional business days. Shipping costs are calculated based on your location and order size during checkout.",
                'category' => 'shipping',
                'sort_order' => 2,
                'is_featured' => false,
            ],

            // Returns & Refunds
            [
                'question' => 'What is your return policy?',
                'answer' => "We want you to be 100% satisfied with your order. Our return policy:\n\n• If there's a printing defect or quality issue, we'll reprint your order at no cost\n• If we made a mistake on your order, you're eligible for a full refund or reprint\n• Returns must be initiated within 7 days of receiving your order\n• Custom products are non-refundable unless there's a defect\n\nContact our support team with photos of any issues for quick resolution.",
                'category' => 'returns',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'question' => 'How do I request a refund?',
                'answer' => "To request a refund:\n\n1. Contact our support team within 7 days of delivery\n2. Provide your order number and photos showing the issue\n3. Our team will review your request within 24 hours\n4. If approved, refunds are processed within 5-7 business days\n5. The amount will be credited to your original payment method\n\nFor urgent issues, call our customer support hotline.",
                'category' => 'returns',
                'sort_order' => 2,
                'is_featured' => false,
            ],

            // Bulk Orders
            [
                'question' => 'Do you offer discounts for bulk orders?',
                'answer' => "Yes! We offer significant discounts for bulk orders. Our pricing structure rewards larger quantities:\n\n• 100-499 pieces: 10% discount\n• 500-999 pieces: 15% discount\n• 1000+ pieces: 20% or more\n\nFor corporate and event orders, contact our bulk orders team for a custom quote. We can also accommodate special packaging and delivery schedules.",
                'category' => 'bulk',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'question' => 'How do I place a bulk order?',
                'answer' => "For bulk orders, we recommend contacting our dedicated bulk orders team:\n\n1. Fill out the Bulk Order Inquiry form on our website\n2. Our team will contact you within 24 hours\n3. We'll discuss your requirements, quantities, and timeline\n4. You'll receive a custom quote with bulk pricing\n5. After approval, we'll start production\n\nBulk orders typically require 5-10 business days for production.",
                'category' => 'bulk',
                'sort_order' => 2,
                'is_featured' => false,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create([
                ...$faq,
                'is_active' => true,
                'views' => rand(10, 500),
                'helpful_count' => rand(5, 50),
                'not_helpful_count' => rand(0, 5),
            ]);
        }
    }
}
