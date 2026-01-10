<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Policy;

class PolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'title' => 'Terms and Conditions',
                'slug' => 'terms',
                'type' => 'terms',
                'meta_title' => 'Terms and Conditions - Mprints',
                'meta_description' => 'Read our terms and conditions for using Mprints printing services.',
                'content' => $this->getTermsContent(),
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy',
                'type' => 'privacy',
                'meta_title' => 'Privacy Policy - Mprints',
                'meta_description' => 'Learn how Mprints collects, uses, and protects your personal information.',
                'content' => $this->getPrivacyContent(),
            ],
            [
                'title' => 'Refund Policy',
                'slug' => 'refund',
                'type' => 'refund',
                'meta_title' => 'Refund Policy - Mprints',
                'meta_description' => 'Understand our refund and return policy for printing orders.',
                'content' => $this->getRefundContent(),
            ],
            [
                'title' => 'Shipping Policy',
                'slug' => 'shipping',
                'type' => 'shipping',
                'meta_title' => 'Shipping Policy - Mprints',
                'meta_description' => 'Information about shipping methods, delivery times, and costs at Mprints.',
                'content' => $this->getShippingContent(),
            ],
        ];

        foreach ($policies as $policy) {
            Policy::create([
                ...$policy,
                'is_active' => true,
                'last_updated_at' => now(),
                'version' => '1.0',
            ]);
        }
    }

    private function getTermsContent(): string
    {
        return <<<EOT
## 1. Introduction

Welcome to Mprints. These Terms and Conditions govern your use of our website located at www.mprints.com and our printing services. By accessing our website or placing an order, you agree to be bound by these terms.

**Please read these terms carefully before using our services.**

## 2. Definitions

- **"We", "Us", "Our"** refers to Mprints, the printing service provider
- **"You", "Your"** refers to the customer or user of our services
- **"Services"** refers to all printing and related services offered by Mprints
- **"Products"** refers to all items available for purchase on our website
- **"Order"** refers to a request for Products placed through our website

## 3. Account Registration

- You must be at least 18 years old to create an account
- You are responsible for maintaining the confidentiality of your account credentials
- You agree to provide accurate and complete information during registration
- You are responsible for all activities that occur under your account

## 4. Orders and Payments

- All orders are subject to acceptance and availability
- Prices are displayed in Indian Rupees (INR) and are exclusive of applicable taxes unless stated otherwise
- Payment must be made in full at the time of ordering
- We accept various payment methods including credit cards, debit cards, UPI, and net banking

## 5. Artwork and Design

- You are responsible for ensuring that your artwork is of sufficient quality for printing
- We are not responsible for errors in customer-provided artwork
- By uploading artwork, you confirm that you have the right to use and reproduce the content
- We reserve the right to refuse any artwork that violates our content guidelines

## 6. Production and Delivery

- Production times are estimates and may vary based on order complexity
- Delivery times are calculated from the date of dispatch, not order placement
- We are not liable for delays caused by shipping carriers or circumstances beyond our control

## 7. Intellectual Property

- All content on our website, including designs, logos, and text, is our intellectual property
- You may not reproduce, distribute, or use our content without written permission
- Customer-uploaded content remains the property of the customer

## 8. Limitation of Liability

- Our liability is limited to the value of the order in question
- We are not liable for indirect, consequential, or incidental damages
- This limitation applies to the fullest extent permitted by law

## 9. Changes to Terms

We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting on our website. Your continued use of our services constitutes acceptance of the modified terms.

## 10. Contact Information

If you have questions about these terms, please contact us:
- Email: support@mprints.com
- Phone: 02522-669393
- Address: Mprints Head Office, Maharashtra, India
EOT;
    }

    private function getPrivacyContent(): string
    {
        return <<<EOT
## 1. Introduction

At Mprints, we are committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or use our services.

## 2. Information We Collect

### Personal Information
We may collect personal information that you voluntarily provide, including:
- Name and contact details (email, phone number, address)
- Billing and shipping addresses
- Payment information
- Account credentials
- Order history and preferences

### Automatically Collected Information
When you visit our website, we automatically collect:
- IP address and browser type
- Device information
- Pages visited and time spent
- Referring website addresses
- Cookies and similar tracking technologies

## 3. How We Use Your Information

We use your information to:
- Process and fulfill your orders
- Communicate with you about your orders and account
- Send promotional emails (with your consent)
- Improve our website and services
- Prevent fraud and ensure security
- Comply with legal obligations

## 4. Information Sharing

We may share your information with:
- **Service Providers:** Third parties who help us operate our business (payment processors, shipping companies)
- **Legal Requirements:** When required by law or to protect our rights
- **Business Transfers:** In connection with a merger, acquisition, or sale of assets

We do not sell your personal information to third parties.

## 5. Data Security

We implement appropriate security measures to protect your information:
- SSL encryption for data transmission
- Secure payment processing
- Regular security audits
- Access controls for employee data access

## 6. Cookies

We use cookies to:
- Remember your preferences
- Analyze website traffic
- Personalize your experience
- Enable shopping cart functionality

You can control cookies through your browser settings.

## 7. Your Rights

You have the right to:
- Access your personal information
- Correct inaccurate information
- Delete your account and data
- Opt-out of marketing communications
- Request data portability

## 8. Children's Privacy

Our services are not intended for children under 18. We do not knowingly collect information from children.

## 9. Updates to This Policy

We may update this policy periodically. The updated version will be indicated by the "Last Updated" date at the top of this page.

## 10. Contact Us

For privacy-related inquiries:
- Email: privacy@mprints.com
- Phone: 02522-669393
EOT;
    }

    private function getRefundContent(): string
    {
        return <<<EOT
## 1. Our Commitment

At Mprints, we strive for 100% customer satisfaction. If you're not happy with your order, we're here to help make it right.

## 2. Refund Eligibility

### Eligible for Refund/Reprint:
- Printing defects (color issues, smudges, misalignment)
- Damaged products during shipping
- Missing items from your order
- Products significantly different from the approved proof
- Wrong quantity delivered

### Not Eligible for Refund:
- Customer-provided artwork errors (spelling, design, resolution)
- Change of mind after production has started
- Slight color variations (due to monitor calibration differences)
- Orders approved by the customer after proof review
- Custom products ordered incorrectly by the customer

## 3. Refund Process

### Step 1: Contact Us
- Email us at support@mprints.com within 7 days of receiving your order
- Include your order number and photos of the issue
- Describe the problem in detail

### Step 2: Review
- Our quality team will review your request within 24-48 hours
- We may request additional photos or information

### Step 3: Resolution
- If approved, we will offer either:
  - Full refund to original payment method
  - Free reprint with expedited shipping
  - Store credit for future orders

## 4. Refund Timeline

- **Approval:** Within 2 business days of receiving complaint
- **Processing:** 5-7 business days after approval
- **Bank Credit:** Depends on your bank (typically 5-10 business days)

## 5. Partial Refunds

In some cases, we may offer partial refunds:
- Minor defects that don't significantly affect usability
- Slight delays in delivery (within reasonable limits)
- Products usable despite minor issues

## 6. Cancellation Policy

- **Before Production:** Full refund available
- **During Production:** 50% refund may be possible
- **After Shipping:** Not eligible for cancellation refund

To cancel, contact us immediately with your order number.

## 7. Exchanges

We do not offer direct exchanges. If you need a different product:
1. Return the original order for a refund
2. Place a new order for the desired product

## 8. Damaged Shipments

If your package arrives damaged:
- Do not discard the packaging
- Take photos of the damage
- Contact us within 48 hours
- We will file a claim with the shipping carrier

## 9. Contact Information

For refund requests:
- Email: support@mprints.com
- Phone: 02522-669393
- Include: Order number, photos, description of issue
EOT;
    }

    private function getShippingContent(): string
    {
        return <<<EOT
## 1. Shipping Overview

Mprints delivers across India with reliable shipping partners. We work hard to ensure your products reach you safely and on time.

## 2. Delivery Options

### Standard Delivery
- **Timeframe:** 5-7 business days
- **Cost:** Calculated at checkout based on location and weight
- **Tracking:** Yes, via email and SMS

### Express Delivery
- **Timeframe:** 2-3 business days
- **Cost:** Additional charges apply
- **Tracking:** Yes, with priority updates

### Same Day Delivery
- **Availability:** Select cities only (Mumbai, Pune, Delhi, Bangalore)
- **Cutoff Time:** Orders placed before 12:00 PM
- **Cost:** Premium charges apply

## 3. Delivery Areas

We deliver to all serviceable pin codes across India, including:
- All major cities and metros
- Tier 2 and Tier 3 cities
- Remote areas (may take additional 2-3 days)

**International Shipping:** Currently not available. Coming soon!

## 4. Processing Time

Processing time is separate from shipping time:
- **Standard Products:** 1-2 business days
- **Custom Products:** 2-3 business days
- **Bulk Orders (100+ units):** 3-5 business days
- **Complex Designs:** May require additional time

*Total delivery time = Processing time + Shipping time*

## 5. Shipping Costs

Shipping costs are calculated based on:
- Delivery location
- Package weight and dimensions
- Chosen shipping method
- Order value (free shipping on orders above ₹999)

### Free Shipping
- Available on orders above ₹999
- Applies to standard delivery only
- Valid for domestic orders only

## 6. Order Tracking

Track your order through:
- Order confirmation email with tracking link
- SMS updates at each delivery milestone
- "Track Order" section on our website
- Customer support team

## 7. Delivery Attempts

- Our carriers make up to 3 delivery attempts
- If undeliverable, the package is returned to us
- Re-shipping charges may apply for returned packages

### Tips for Successful Delivery:
- Provide accurate address and pin code
- Include a working phone number
- Mention landmarks if needed
- Ensure someone is available to receive

## 8. Shipping Delays

Delays may occur due to:
- Weather conditions
- Public holidays
- High order volumes (festive seasons)
- Remote location accessibility
- Carrier issues

We proactively communicate any expected delays.

## 9. Damaged or Lost Packages

### Damaged Packages:
- Report within 48 hours with photos
- Do not discard packaging materials
- We will arrange replacement or refund

### Lost Packages:
- Contact us if package not received within expected timeframe
- We will initiate investigation with carrier
- Replacement or refund provided if package confirmed lost

## 10. Contact for Shipping Queries

- Email: shipping@mprints.com
- Phone: 02522-669393
- Hours: Monday to Saturday, 9:00 AM to 6:00 PM
EOT;
    }
}
