import { NextRequest, NextResponse } from 'next/server';

// In-memory OTP store (use Redis/DB in production)
const otpStore = new Map<string, { otp: string; expiresAt: number }>();

export async function POST(request: NextRequest) {
  try {
    const { mobileNumber } = await request.json();
    
    if (!mobileNumber || mobileNumber.length < 10) {
      return NextResponse.json(
        { success: false, error: 'Invalid mobile number' },
        { status: 400 }
      );
    }

    // Generate 6-digit OTP
    const otp = Math.floor(100000 + Math.random() * 900000).toString();
    
    // Store OTP with 5-minute expiry
    otpStore.set(mobileNumber, {
      otp,
      expiresAt: Date.now() + 5 * 60 * 1000,
    });

    // TODO: In production, integrate with WhatsApp Business API or SMS gateway
    // Options:
    // 1. Twilio WhatsApp API
    // 2. Gupshup
    // 3. MSG91
    // 4. Interakt
    
    console.log(`[OTP] Sent to ${mobileNumber}: ${otp}`); // Dev only

    return NextResponse.json({
      success: true,
      message: 'OTP sent successfully via WhatsApp',
    });
  } catch (error) {
    console.error('OTP send error:', error);
    return NextResponse.json(
      { success: false, error: 'Failed to send OTP' },
      { status: 500 }
    );
  }
}

// Export store for verification route
export { otpStore };
