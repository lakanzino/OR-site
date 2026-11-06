package ir.clinic.secretary

import android.os.Bundle
import android.view.View
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.fragment.app.Fragment
import ir.clinic.secretary.data.Patient
import ir.clinic.secretary.databinding.ActivityMainBinding
import ir.clinic.secretary.ui.PatientsFragment
import ir.clinic.secretary.ui.RegisterFragment
import ir.clinic.secretary.ui.ReportsFragment
import ir.clinic.secretary.ui.SettingsFragment
import ir.clinic.secretary.util.PersianDate

class MainActivity : AppCompatActivity() {
    private lateinit var binding: ActivityMainBinding
    private val vm: MainViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.decorView.layoutDirection = View.LAYOUT_DIRECTION_RTL
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)
        vm.settings.observe(this) { s ->
            binding.tvDoctorName.text = s.doctorName
            binding.tvSpecialty.text = s.specialty
        }
        binding.bottomNav.setOnItemSelectedListener { item ->
            when (item.itemId) {
                R.id.nav_register -> show(RegisterFragment())
                R.id.nav_patients -> show(PatientsFragment())
                R.id.nav_reports -> show(ReportsFragment())
                R.id.nav_settings -> show(SettingsFragment())
            }
            true
        }
        if (savedInstanceState == null) binding.bottomNav.selectedItemId = R.id.nav_register
    }

    override fun onResume() {
        super.onResume()
        binding.tvDate.text = PersianDate.today().longFormat()
    }

    fun editPatient(patient: Patient) {
        vm.beginEdit(patient)
        if (binding.bottomNav.selectedItemId == R.id.nav_register) show(RegisterFragment())
        else binding.bottomNav.selectedItemId = R.id.nav_register
    }

    fun restartRegister() = show(RegisterFragment())

    private fun show(f: Fragment) {
        supportFragmentManager.beginTransaction().replace(R.id.fragmentContainer, f).commit()
    }
}
