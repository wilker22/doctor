<?php

namespace App\Http\Controllers;

use App\Appointment;
use App\Booking;
use App\Time;
use App\User;
use Illuminate\Http\Request;
use PhpParser\Node\Stmt\TryCatch;
use App\Mail\AppointmentMail;
use App\Prescription;

class FrontendController extends Controller
{
    public function index()
    {
        // DEFINE O FUSO HORARIO COMO O HORARIO DE BRASILIA
        date_default_timezone_set('America/Sao_Paulo');

        if(request('date')){
            $doctors = $this->findDoctorsBasedOnDate(request('date'));
            return view('welcome', compact('doctors'));

        }
        $doctors = Appointment::where('date', date('d-m-Y'))->get();
        return view('welcome', compact('doctors'));
    }

    public function show($doctorId, $date)
    {
        $appointment = Appointment::where('user_id', $doctorId)->where('date', $date)->first();
        $times = Time::where('appointment_id', $appointment->id)->where('status', 0)->get();
        $user = User::where('id', $doctorId)->first();
        $doctor_id = $doctorId;
        return view('appointment', compact('times', 'date', 'user', 'doctor_id'));
    }

    public function  findDoctorsBasedOnDate($date)
    {
        $doctors = Appointment::where('date', $date)->get();
        return $doctors;
    }

    public function store(Request $request)
    {
        $request->validate(['time'=> 'required']);
        $check = $this->checkBookingTimeInterval();
        if($check){
            return redirect()->back()->with('errmessage', 'Você já tem agendamento para hoje!');
        }

        Booking::create([
            'user_id' => auth()->user()->id,
            'doctor_id' => $request->doctorId,
            'time' => $request->time,
            'date' => $request->date,
            'status' => 0
        ]);

        Time::where('appointment_id', $request->appointmentId)
            ->where('time', $request->time)
            ->update(['status' => 1]);

        //send e-mail notification
        $doctorName = User::where('id', $request->doctorId)->first();
        $mailData = [
            'name' => auth()->user()->name,
            'time' => $request->time,
            'date' => $request->date,
            'doctorName' =>  $doctorName->name
        ];


        try{
            \Mail::to(auth()->user()->email)->send(new AppointmentMail($mailData));
        }catch(\Exception $e){
            return $e;
        }

        return redirect()->back()->with('message', 'Seu Agendamento foi confirmado!');
    }

    public function checkBookingTimeInterval()
    {
        return Booking::orderby('id', 'desc')
                    ->where('user_id', auth()->user()->id)
                    ->whereDate('created_at', date('Y-m-d'))
                    ->exists();
    }

    public function myBookings()
    {
        $appointments = Booking::latest()->where('user_id', auth()->user()->id)->get();
        return view('booking.index', compact('appointments'));
    }

    public function myPrescription()
    {
        $prescriptions = Prescription::where('user_id', auth()->user()->id)->get();
        return view('my-prescription', compact('prescriptions'));
    }

    public function doctorToday(Request $request)
    {
        $doctors = Appointment::with('doctor')->whereDate('date', date('d-m-Y'))->get();
        return $doctors;
    }

    public function findDoctors(Request $request)
    {
        $doctors = Appointment::with('doctor')->whereDate('date',$request->date)->get();
        return $doctors;
    }


}
