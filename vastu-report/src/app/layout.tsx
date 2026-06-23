import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
});

export const metadata: Metadata = {
  title: "Shilaavinyaas - Vastu Report Generator",
  description: "Get a personalised Vastu analysis report for your property. Upload your floor plan and receive directional insights based on ancient Vastu Shastra principles.",
  keywords: "vastu, vastu shastra, floor plan analysis, vastu report, vastu consultancy",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className={`${inter.variable} font-sans antialiased`}>
        {children}
      </body>
    </html>
  );
}
