package com.hamyarj.clinic

import android.app.Application
import com.hamyarj.clinic.data.AppDatabase
import com.hamyarj.clinic.data.ClinicRepository

class HamyarApplication : Application() {
    /** Lazily created so the DB is only opened when first needed. */
    val repository: ClinicRepository by lazy { ClinicRepository(AppDatabase.getInstance(this)) }
}
